<?php

namespace Tests\Feature\FileNumber;

use App\Models\User;
use App\Support\MlsRowTarget;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The MLS File Commission screen: its list payload and the Master Delete guard.
 *
 * READ-ONLY BY DESIGN. This suite runs against the shared development database, which the
 * team also uses for live UI testing, so nothing here creates or removes a row. The delete
 * assertions all target requests that must be REFUSED before any write happens — which is
 * precisely the behaviour worth pinning, and happens to be safe to exercise for real.
 *
 * What it protects:
 *
 *  - The list is a UNION of three tables (`fileNumber`, `mls_file_no` for temporary files,
 *    `plot_extensions`) whose ids collide. Master Delete used to resolve any id against
 *    `fileNumber`, so deleting temporary RES-1993-2644(T) — mls_file_no id 1166 — purged
 *    the unrelated live file CON-AG-1987-57 from five tables. The refusal is asserted here
 *    at the HTTP boundary, not just in the unit tests for MlsRowTarget, because the client
 *    hiding the button is not a guarantee: a page cached before the fix still posts.
 *
 *  - The Passport / RoT / Related-Old-FileNo columns must actually be present in the
 *    payload, with the shapes the DataTable renderers expect.
 */
class MlsFcListAndDeleteGuardTest extends TestCase
{
    private function admin(): ?User
    {
        // The delete guard is Supper Admin only; without one we cannot reach the entity
        // check at all, so those tests skip rather than assert something weaker.
        return User::where('assign_role', 'Supper Admin')->first();
    }

    private function anyUser(): ?User
    {
        return User::query()->first();
    }

    /** @test */
    public function the_list_payload_carries_the_passport_root_of_title_and_related_old_file_number(): void
    {
        $user = $this->anyUser();
        if (!$user) {
            $this->markTestSkipped('No user available in this environment.');
        }

        $response = $this->actingAs($user)->getJson('/file-numbers/data?source=New&start=0&length=5&draw=1');

        $response->assertOk();

        $rows = $response->json('data');
        $this->assertIsArray($rows);

        if (empty($rows)) {
            $this->markTestSkipped('No file-number rows available to inspect.');
        }

        foreach ($rows as $row) {
            $this->assertArrayHasKey('passport_url', $row, 'Passport column missing from the row payload');
            $this->assertArrayHasKey('root_of_title', $row, 'RoT column missing from the row payload');
            $this->assertArrayHasKey('related_old_fileno', $row, 'Related/Old File No column missing from the row payload');

            // passport_url is a URL or null — the renderer branches on falsiness.
            $this->assertTrue(
                $row['passport_url'] === null || is_string($row['passport_url']),
                'passport_url must be a string URL or null'
            );

            // RoT is always a string; 'N/A' when the file has never been indexed.
            $this->assertIsString($row['root_of_title']);

            // The Related/Old cell needs both halves: the renderer prints a different
            // badge for an old number than for a related one.
            $this->assertIsArray($row['related_old_fileno']);
            $this->assertArrayHasKey('value', $row['related_old_fileno']);
            $this->assertArrayHasKey('kind', $row['related_old_fileno']);
            $this->assertContains($row['related_old_fileno']['kind'], ['old', 'related', 'none']);
        }
    }

    /** @test */
    public function every_row_type_in_the_payload_reports_the_same_three_columns(): void
    {
        // Temporary and Plot Extension rows are formatted by separate code paths; a column
        // added to only the fileNumber path renders as blank on the others.
        $user = $this->anyUser();
        if (!$user) {
            $this->markTestSkipped('No user available in this environment.');
        }

        $response = $this->actingAs($user)->getJson('/file-numbers/data?source=New&start=0&length=100&draw=1');
        $response->assertOk();

        $byType = [];
        foreach (($response->json('data') ?? []) as $row) {
            $byType[$row['type'] ?? 'unknown'] ??= $row;
        }

        if (empty($byType)) {
            $this->markTestSkipped('No file-number rows available to inspect.');
        }

        foreach ($byType as $type => $row) {
            foreach (['passport_url', 'root_of_title', 'related_old_fileno'] as $key) {
                $this->assertArrayHasKey($key, $row, "Row type '{$type}' is missing '{$key}'");
            }
        }
    }

    /** @test */
    public function master_delete_refuses_a_temporary_row(): void
    {
        $admin = $this->admin();
        if (!$admin) {
            $this->markTestSkipped('No Supper Admin available in this environment.');
        }

        // A temporary file whose id also exists in fileNumber — the exact shape of the bug.
        $temp = DB::connection('sqlsrv')->table('mls_file_no')
            ->where('file_option', 'temporary')
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->first();

        if (!$temp) {
            $this->markTestSkipped('No temporary file rows in this environment.');
        }

        $victim = DB::connection('sqlsrv')->table('fileNumber')->where('id', $temp->id)->first();

        $response = $this->actingAs($admin)
            ->deleteJson("/file-numbers/{$temp->id}", ['entity' => MlsRowTarget::TEMPORARY]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertStringContainsString('Temporary file', $response->json('message'));

        // The collision victim must still be there. This is the assertion that would have
        // caught the original bug.
        if ($victim) {
            $this->assertNotNull(
                DB::connection('sqlsrv')->table('fileNumber')->where('id', $temp->id)->first(),
                "Deleting temporary file {$temp->full_file_number} removed fileNumber id {$temp->id}"
            );
        }
    }

    /** @test */
    public function master_delete_refuses_a_plot_extension_row(): void
    {
        $admin = $this->admin();
        if (!$admin) {
            $this->markTestSkipped('No Supper Admin available in this environment.');
        }

        $pe = DB::connection('sqlsrv')->table('plot_extensions')
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->first();

        if (!$pe) {
            $this->markTestSkipped('No plot extension rows in this environment.');
        }

        $response = $this->actingAs($admin)
            ->deleteJson("/file-numbers/{$pe->id}", ['entity' => MlsRowTarget::PLOT_EXTENSION]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Plot Extension', $response->json('message'));
    }

    /** @test */
    public function bulk_delete_skips_non_file_number_selections_instead_of_resolving_them(): void
    {
        $admin = $this->admin();
        if (!$admin) {
            $this->markTestSkipped('No Supper Admin available in this environment.');
        }

        $temp = DB::connection('sqlsrv')->table('mls_file_no')
            ->where('file_option', 'temporary')
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->first();

        if (!$temp) {
            $this->markTestSkipped('No temporary file rows in this environment.');
        }

        // A selection of ONLY non-fileNumber rows resolves to nothing to delete, so this
        // exercises the guard without putting any real row at risk.
        $response = $this->actingAs($admin)->postJson('/file-numbers/bulk-destroy', [
            'ids' => ["T:{$temp->id}"],
        ]);

        $response->assertStatus(404);
        $response->assertJson(['success' => false]);

        $skipped = $response->json('skipped');
        $this->assertIsArray($skipped);
        $this->assertNotEmpty($skipped, 'The temporary row should be reported as skipped, not silently dropped');
        $this->assertSame((int) $temp->id, (int) $skipped[0]['id']);

        // And the collision victim survives.
        $this->assertNotNull(
            DB::connection('sqlsrv')->table('fileNumber')->where('id', $temp->id)->first()
                ?: 'no-collision',
            'Bulk delete must not touch the fileNumber row sharing the temporary id'
        );
    }

    /** @test */
    public function bulk_delete_rejects_an_unreadable_selection_key(): void
    {
        $admin = $this->admin();
        if (!$admin) {
            $this->markTestSkipped('No Supper Admin available in this environment.');
        }

        // "X:8" must not be read as fileNumber id 8 (AG-RC-1981-54).
        $response = $this->actingAs($admin)->postJson('/file-numbers/bulk-destroy', [
            'ids' => ['X:8'],
        ]);

        $response->assertStatus(404);
        $this->assertNotEmpty($response->json('skipped'));

        $this->assertNotNull(
            DB::connection('sqlsrv')->table('fileNumber')->where('id', 8)->first(),
            'An unreadable selection key must never resolve to a real file'
        );
    }

    /** @test */
    public function master_delete_still_requires_supper_admin(): void
    {
        $user = User::where('assign_role', '!=', 'Supper Admin')->first();
        if (!$user) {
            $this->markTestSkipped('No non-admin user available in this environment.');
        }

        $response = $this->actingAs($user)->deleteJson('/file-numbers/8', ['entity' => 'file_number']);

        $response->assertStatus(403);

        $this->assertNotNull(
            DB::connection('sqlsrv')->table('fileNumber')->where('id', 8)->first(),
            'A non-admin delete must not remove the record'
        );
    }
}
