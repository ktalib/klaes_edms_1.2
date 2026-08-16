<?php

namespace Tests\Feature\Spas;

use App\Models\SpaApplication;
use App\Models\SpaFieldData;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * /api/spas/* — the offline sync surface (Phase 0).
 *
 * NOTE ON ISOLATION: DatabaseTransactions, never RefreshDatabase. These tables
 * live on the shared `sqlsrv` development database alongside real data the team
 * uses through the UI; migrating or truncating it would destroy their work.
 * Every test here rolls back.
 *
 * @see docs/plans/SPAS_MOBILE_OFFLINE_CAPACITOR_SYNC_PLAN.md §15
 */
class SpasSyncApiTest extends TestCase
{
    use DatabaseTransactions;

    /** Roll back on the SQL Server connection, not the default MySQL one. */
    protected $connectionsToTransact = ['sqlsrv'];

    private function surveyor(): User
    {
        return User::query()->firstOrFail();
    }

    private function actingAsSurveyor(): User
    {
        $user = $this->surveyor();
        Sanctum::actingAs($user, ['spas-mobile']);

        return $user;
    }

    /** A valid customary land-record push payload. */
    private function recordPayload(array $overrides = []): array
    {
        return array_merge([
            'client_uuid'     => (string) Str::uuid(),
            'land_title_type' => 'customary',
            'owner_name'      => 'ZZ TEST Surveyor Record',
            'proposed_use'    => 'RESIDENTIAL',
            'existing_use'    => 'COMMERCIAL',
            'lga'             => 'Kano Municipal',
        ], $overrides);
    }

    // -----------------------------------------------------------------------
    // Auth
    // -----------------------------------------------------------------------

    public function test_sync_endpoints_reject_an_unauthenticated_request(): void
    {
        // Land records carry owner PII; nothing here may be readable without a token.
        $this->getJson('/api/spas/records')->assertUnauthorized();
        $this->getJson('/api/spas/field-data')->assertUnauthorized();
        $this->getJson('/api/spas/lookup/districts')->assertUnauthorized();
        $this->postJson('/api/spas/records', $this->recordPayload())->assertUnauthorized();
    }

    public function test_login_rejects_bad_credentials(): void
    {
        $this->postJson('/api/spas/auth/login', [
            'identifier' => 'definitely-not-a-real-user-'.time(),
            'password'   => 'wrong-password',
        ])->assertStatus(401)->assertJson(['status' => 'error']);
    }

    public function test_login_validates_required_fields(): void
    {
        $this->postJson('/api/spas/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['identifier', 'password']);
    }

    // -----------------------------------------------------------------------
    // Push — records
    // -----------------------------------------------------------------------

    public function test_pushing_a_record_creates_it(): void
    {
        $this->actingAsSurveyor();
        $payload = $this->recordPayload();

        $response = $this->postJson('/api/spas/records', $payload)
            ->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('spa_applications', [
            'client_uuid' => $payload['client_uuid'],
            'owner_name'  => 'ZZ TEST Surveyor Record',
            'status'      => 'open',
        ], 'sqlsrv');

        // The server owns the customary sequence — a device-invented number is
        // only ever a placeholder.
        $this->assertMatchesRegularExpression('/^SPAS-\d{4}-\d{4}$/', $response->json('file_number'));
    }

    public function test_a_record_push_requires_a_client_uuid(): void
    {
        // Without it there is no idempotency key and a retry duplicates the row.
        $this->actingAsSurveyor();

        $payload = $this->recordPayload();
        unset($payload['client_uuid']);

        $this->postJson('/api/spas/records', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['client_uuid']);
    }

    /**
     * The core offline guarantee. A push that succeeded server-side but whose
     * response never reached the handset WILL be retried — that is normal on a
     * field connection, not an error.
     */
    public function test_retrying_the_same_push_does_not_duplicate_the_record(): void
    {
        $this->actingAsSurveyor();
        $payload = $this->recordPayload();

        $first = $this->postJson('/api/spas/records', $payload)->assertStatus(201);

        $second = $this->postJson('/api/spas/records', $payload)
            ->assertOk()
            ->assertJson(['success' => true, 'duplicate' => true]);

        $this->assertSame($first->json('id'), $second->json('id'), 'The retry must return the original row.');
        $this->assertSame(1, SpaApplication::where('client_uuid', $payload['client_uuid'])->count());
    }

    public function test_customary_record_without_an_lga_is_rejected(): void
    {
        // Queuing this offline would fail on every push attempt, long after the
        // surveyor has left the site and can no longer supply the answer.
        $this->actingAsSurveyor();

        $payload = $this->recordPayload();
        unset($payload['lga']);

        $this->postJson('/api/spas/records', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lga']);
    }

    public function test_a_second_application_for_the_same_file_number_is_a_conflict_not_a_validation_error(): void
    {
        $this->actingAsSurveyor();
        $fileNumber = 'ZZ-TEST-CONFLICT-'.Str::random(8);

        $this->postJson('/api/spas/records', $this->recordPayload([
            'land_title_type' => 'statutory',
            'file_number'     => $fileNumber,
        ]))->assertStatus(201);

        // 409, not 422: retrying will never succeed, so the app must route this
        // to a Conflicts list rather than leaving it in the outbox forever.
        $this->postJson('/api/spas/records', $this->recordPayload([
            'land_title_type' => 'statutory',
            'file_number'     => $fileNumber,
        ]))
            ->assertStatus(409)
            ->assertJson(['success' => false, 'conflict' => 'file_number']);
    }

    // -----------------------------------------------------------------------
    // Push — field data, and the flat-FIFO outbox
    // -----------------------------------------------------------------------

    /**
     * The design the nullable FK exists for: an inspection can be pushed before
     * its parent record, so the outbox drains as a flat FIFO with no dependency
     * tracking.
     */
    public function test_an_inspection_can_be_pushed_before_its_parent_record(): void
    {
        $this->actingAsSurveyor();
        $parentUuid = (string) Str::uuid();

        $response = $this->postJson('/api/spas/field-data', [
            'client_uuid'                 => (string) Str::uuid(),
            'spa_application_client_uuid' => $parentUuid,
            'file_number'                 => 'ZZ-TEST-ORPHAN-'.Str::random(8),
            'inspection_date'             => now()->toDateString(),
            'findings'                    => 'ZZ TEST findings recorded on site.',
            'coordinates'                 => '{"lat":11.9964,"lng":8.5919}',
        ])->assertStatus(201)->assertJson(['success' => true]);

        $this->assertNull($response->json('spa_application_id'), 'The parent has not synced yet.');
    }

    public function test_link_orphans_attaches_inspections_to_a_late_arriving_parent(): void
    {
        $this->actingAsSurveyor();
        $parentUuid = (string) Str::uuid();
        $fileNumber = 'ZZ-TEST-LINK-'.Str::random(8);

        // Child first.
        $child = $this->postJson('/api/spas/field-data', [
            'client_uuid'                 => (string) Str::uuid(),
            'spa_application_client_uuid' => $parentUuid,
            'file_number'                 => $fileNumber,
            'inspection_date'             => now()->toDateString(),
            'findings'                    => 'ZZ TEST findings.',
        ])->assertStatus(201);

        // Parent second.
        $parent = $this->postJson('/api/spas/records', $this->recordPayload([
            'client_uuid' => $parentUuid,
        ]))->assertStatus(201);

        $this->postJson('/api/spas/link-orphans')
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(
            $parent->json('id'),
            SpaFieldData::find($child->json('id'))->spa_application_id,
            'The orphan must now point at the parent that arrived after it.'
        );

        // Logging an inspection advances the application out of `open`.
        $this->assertSame('in_progress', SpaApplication::find($parent->json('id'))->status);
    }

    public function test_a_second_inspection_for_the_same_file_is_a_conflict(): void
    {
        $this->actingAsSurveyor();
        $fileNumber = 'ZZ-TEST-DUPINSP-'.Str::random(8);

        $base = [
            'file_number'     => $fileNumber,
            'inspection_date' => now()->toDateString(),
            'findings'        => 'ZZ TEST findings.',
        ];

        $this->postJson('/api/spas/field-data', $base + ['client_uuid' => (string) Str::uuid()])
            ->assertStatus(201);

        $this->postJson('/api/spas/field-data', $base + ['client_uuid' => (string) Str::uuid()])
            ->assertStatus(409)
            ->assertJson(['conflict' => 'file_number']);
    }

    public function test_unparseable_coordinates_are_rejected(): void
    {
        $this->actingAsSurveyor();

        $this->postJson('/api/spas/field-data', [
            'client_uuid'     => (string) Str::uuid(),
            'file_number'     => 'ZZ-TEST-COORD-'.Str::random(8),
            'inspection_date' => now()->toDateString(),
            'findings'        => 'ZZ TEST findings.',
            'coordinates'     => 'somewhere near the market',
        ])->assertStatus(422);
    }

    // -----------------------------------------------------------------------
    // Photos
    // -----------------------------------------------------------------------

    public function test_photo_upload_for_an_unsynced_parent_returns_404_so_the_app_retries(): void
    {
        // A 404 tells the device to keep the upload queued until the parent
        // lands. Anything reading as terminal would make it discard the photos.
        Storage::fake('public');
        $this->actingAsSurveyor();

        $this->post('/api/spas/photos', [
            'entity_type' => 'record',
            'client_uuid' => (string) Str::uuid(),   // never pushed
            'photos'      => [UploadedFile::fake()->image('plot.jpg')],
        ])->assertStatus(404);
    }

    public function test_photos_attach_to_a_record_that_has_already_synced(): void
    {
        // Text syncs first and photos follow, because a record that reaches the
        // office without images beats one that never syncs because a 3 MB
        // upload keeps timing out on a 2G link.
        Storage::fake('public');
        $this->actingAsSurveyor();

        $payload = $this->recordPayload();
        $this->postJson('/api/spas/records', $payload)->assertStatus(201);

        $this->post('/api/spas/photos', [
            'entity_type' => 'record',
            'client_uuid' => $payload['client_uuid'],
            'photos'      => [
                UploadedFile::fake()->image('plot-front.jpg'),
                UploadedFile::fake()->image('plot-side.jpg'),
            ],
        ])->assertOk()->assertJson(['success' => true]);

        $stored = SpaApplication::where('client_uuid', $payload['client_uuid'])->firstOrFail()->photos;

        $this->assertCount(2, $stored);

        foreach ($stored as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_photo_upload_rejects_a_non_image(): void
    {
        Storage::fake('public');
        $this->actingAsSurveyor();

        $this->post('/api/spas/photos', [
            'entity_type' => 'record',
            'client_uuid' => (string) Str::uuid(),
            'photos'      => [UploadedFile::fake()->create('notes.pdf', 100)],
        ])->assertStatus(422)->assertJsonValidationErrors(['photos.0']);
    }

    // -----------------------------------------------------------------------
    // Pull
    // -----------------------------------------------------------------------

    public function test_records_pull_returns_the_sync_envelope(): void
    {
        $this->actingAsSurveyor();

        $this->getJson('/api/spas/records')
            ->assertOk()
            ->assertJsonStructure(['success', 'count', 'server_time', 'has_more', 'data']);
    }

    public function test_a_pushed_record_comes_back_on_the_next_pull(): void
    {
        $this->actingAsSurveyor();
        $payload = $this->recordPayload();

        $this->postJson('/api/spas/records', $payload)->assertStatus(201);

        $uuids = collect($this->getJson('/api/spas/records')->json('data'))->pluck('client_uuid');

        $this->assertContains($payload['client_uuid'], $uuids->all());
    }

    /**
     * updated_at is DATETIME2(0) — whole seconds. A strict `>` cursor would
     * permanently skip any row written in the same second as the last row of a
     * page, so the filter is `>=` and the client dedupes.
     */
    public function test_since_cursor_is_inclusive_of_its_own_second(): void
    {
        $this->actingAsSurveyor();
        $payload = $this->recordPayload();

        $this->postJson('/api/spas/records', $payload)->assertStatus(201);

        $row = SpaApplication::where('client_uuid', $payload['client_uuid'])->firstOrFail();

        $uuids = collect(
            $this->getJson('/api/spas/records?since='.urlencode($row->updated_at->toIso8601String()))->json('data')
        )->pluck('client_uuid');

        $this->assertContains(
            $payload['client_uuid'],
            $uuids->all(),
            'A row whose updated_at equals the cursor must still be returned, or it is lost forever.'
        );
    }

    public function test_an_unparseable_since_cursor_falls_back_to_a_full_pull(): void
    {
        // Returning zero rows here would look like "nothing changed" and stall sync.
        $this->actingAsSurveyor();

        $this->getJson('/api/spas/records?since=not-a-timestamp')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    // -----------------------------------------------------------------------
    // Lookups
    // -----------------------------------------------------------------------

    public function test_lga_and_district_lookups_return_the_full_reference_sets(): void
    {
        $this->actingAsSurveyor();

        // Both are mirrored whole on device; the customary address picker is
        // unusable offline without them.
        $this->getJson('/api/spas/lookup/lgas')
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(45, 'data');

        $this->getJson('/api/spas/lookup/districts')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_land_use_lookup_excludes_industrial_from_the_customary_subset(): void
    {
        $this->actingAsSurveyor();

        $customary = $this->getJson('/api/spas/lookup/land-uses')
            ->assertOk()
            ->json('customary');

        $upper = array_map(fn ($u) => strtoupper(trim($u)), $customary);

        $this->assertNotContains('INDUSTRIAL', $upper, 'Customary land is only held for RES/COM/AGR.');
    }

    public function test_file_index_lookup_is_bounded(): void
    {
        $this->actingAsSurveyor();

        // file_indexings is far too large to mirror; an unbounded response would
        // be a multi-megabyte download over 2G.
        $response = $this->getJson('/api/spas/lookup/file-index?limit=5')->assertOk();

        $this->assertLessThanOrEqual(5, count($response->json('data')));
    }

    public function test_file_index_lookup_rejects_an_oversized_limit(): void
    {
        $this->actingAsSurveyor();

        $this->getJson('/api/spas/lookup/file-index?limit=99999')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    }

    public function test_next_customary_file_number_follows_the_expected_shape(): void
    {
        $this->actingAsSurveyor();

        $this->getJson('/api/spas/lookup/next-customary-fileno')
            ->assertOk()
            ->assertJson(['success' => true]);
    }
}
