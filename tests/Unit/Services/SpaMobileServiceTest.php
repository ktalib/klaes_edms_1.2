<?php

namespace Tests\Unit\Services;

use App\Services\SpaMobileService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * SpaMobileService — the parts that need no database.
 *
 * These lock down the rule set that three front-ends now share: the desktop
 * Blade form, the mobile Blade form, and the offline Capacitor app's
 * client-side mirror. A rule silently disappearing from here is exactly the
 * failure that broke every customary mobile save in August 2026, so the rules
 * are asserted individually rather than as a blob.
 *
 * @see docs/plans/SPAS_MOBILE_OFFLINE_CAPACITOR_SYNC_PLAN.md §15
 */
class SpaMobileServiceTest extends TestCase
{
    private SpaMobileService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SpaMobileService();
    }

    // -----------------------------------------------------------------------
    // Land record rules
    // -----------------------------------------------------------------------

    public function test_land_record_rules_require_the_core_fields(): void
    {
        $rules = $this->service->landRecordRules();

        $this->assertSame('required|in:statutory,customary', $rules['land_title_type']);
        $this->assertSame('required|string|max:255', $rules['owner_name']);
        $this->assertSame('required|string|max:255', $rules['proposed_use']);
        $this->assertSame('required|string|max:255', $rules['existing_use']);
    }

    /**
     * The regression that started all of this. A customary title has no indexed
     * file to inherit an address from, so LGA is the minimum needed to place it
     * — and the mobile form had no LGA field when this rule was added.
     */
    public function test_lga_is_required_only_for_customary_titles(): void
    {
        $rules = $this->service->landRecordRules();

        $this->assertStringContainsString(
            'required_if:land_title_type,customary',
            $rules['lga'],
            'The customary LGA requirement must survive — its absence, or its addition without the matching form field, is what caused the Aug-2026 silent 422.'
        );

        $this->assertStringNotContainsString('required', $rules['district'], 'District is optional.');
    }

    public function test_file_number_is_required_only_for_statutory_titles(): void
    {
        // Customary numbers are generated server-side, so the client never sends one.
        $this->assertStringContainsString(
            'required_if:land_title_type,statutory',
            $this->service->landRecordRules()['file_number']
        );
    }

    public function test_lga_rule_carries_a_human_readable_message(): void
    {
        // Mobile surfaced only a generic "Save failed" before; the message is
        // what makes the rejection actionable in the field.
        $this->assertSame(
            'Please select the LGA for this customary title.',
            $this->service->landRecordMessages()['lga.required_if']
        );
    }

    public function test_photo_rules_can_be_omitted_for_the_offline_push_path(): void
    {
        $this->assertArrayHasKey('photos.*', $this->service->landRecordRules());
        $this->assertArrayNotHasKey('photos.*', $this->service->landRecordRules(withPhotos: false));
    }

    // -----------------------------------------------------------------------
    // Field data rules
    // -----------------------------------------------------------------------

    /**
     * The offline app may push an inspection before its parent record exists on
     * the server, linking by client_uuid instead. Requiring a server id here
     * would force the outbox back into dependency ordering.
     */
    public function test_field_data_parent_id_is_optional_by_default(): void
    {
        $rules = $this->service->fieldDataRules();

        $this->assertStringStartsWith('nullable', $rules['spa_application_id']);
        $this->assertArrayHasKey('spa_application_client_uuid', $rules);
    }

    public function test_field_data_parent_id_can_be_made_required_for_web_forms(): void
    {
        // The Blade forms always have a saved parent, so they keep the stricter rule.
        $this->assertStringStartsWith(
            'required',
            $this->service->fieldDataRules(requireServerParent: true)['spa_application_id']
        );
    }

    public function test_field_data_always_requires_a_date_and_findings(): void
    {
        $rules = $this->service->fieldDataRules();

        $this->assertSame('required|date', $rules['inspection_date']);
        $this->assertSame('required|string', $rules['findings']);
    }

    // -----------------------------------------------------------------------
    // Coordinate normalisation
    // -----------------------------------------------------------------------

    public function test_accepts_the_json_object_the_map_picker_posts(): void
    {
        $this->assertSame(
            ['lat' => 11.9964, 'lng' => 8.5919],
            $this->service->normalizeCoordinates('{"lat":11.9964,"lng":8.5919}')
        );
    }

    public function test_accepts_an_already_decoded_array_from_the_json_api(): void
    {
        $this->assertSame(
            ['lat' => 11.9964, 'lng' => 8.5919],
            $this->service->normalizeCoordinates(['lat' => 11.9964, 'lng' => 8.5919])
        );
    }

    public function test_accepts_a_raw_lat_lng_string(): void
    {
        $this->assertSame(
            ['lat' => 11.9964, 'lng' => 8.5919],
            $this->service->normalizeCoordinates('11.9964, 8.5919')
        );
    }

    public function test_handles_negative_coordinates(): void
    {
        // Kano is northern-hemisphere/eastern, but the parser must not assume it.
        $this->assertSame(
            ['lat' => -11.5, 'lng' => -8.25],
            $this->service->normalizeCoordinates('-11.5, -8.25')
        );
    }

    /** @dataProvider emptyCoordinateProvider */
    public function test_empty_input_is_null_rather_than_an_error($input): void
    {
        // Coordinates are genuinely optional — an inspection with no pin is valid.
        $this->assertNull($this->service->normalizeCoordinates($input));
    }

    public static function emptyCoordinateProvider(): array
    {
        return [
            'null'         => [null],
            'empty string' => [''],
            'empty array'  => [[]],
        ];
    }

    /**
     * Degrading to "no pin" here would silently discard the one piece of data
     * the surveyor physically travelled to collect.
     */
    public function test_unparseable_coordinates_throw_rather_than_save_a_blank_pin(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->normalizeCoordinates('somewhere near the market');
    }

    public function test_a_single_number_is_not_enough_for_a_pin(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->normalizeCoordinates('11.9964');
    }

    public function test_coordinate_error_message_tells_the_surveyor_what_to_do(): void
    {
        try {
            $this->service->normalizeCoordinates('nonsense');
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('re-pick the pin', $e->validator->errors()->first('coordinates'));
        }
    }
}
