<?php

namespace Tests\Unit\Services;

use App\Services\FileLocationResolver;
use App\Services\RegistryDetector;
use Tests\TestCase;

class RegistryDetectorTest extends TestCase
{
    protected RegistryDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new RegistryDetector(new FileLocationResolver());
    }

    /**
     * @dataProvider registryCases
     */
    public function test_detects_expected_registry(string $fileNumber, ?int $expectedRegistry): void
    {
        $this->assertSame($expectedRegistry, $this->detector->detectNumber($fileNumber));
    }

    public static function registryCases(): array
    {
        return [
            // Registry 1 — RES only 1981-1991, everything else in the family is 1981-2026.
            'RES within 1981-1991'  => ['RES-1988-123', 1],
            'RES 1981 lower bound'  => ['RES-1981-1', 1],
            'RES 1991 upper bound'  => ['RES-1991-1', 1],
            'COM 1981-2026'         => ['COM-2010-44', 1],
            'IND-RC 1981-2026'      => ['IND-RC-2005-9', 1],
            'AG-RC 1981-2026'       => ['AG-RC-1981-1', 1],
            'RES-RC 1981-2026'      => ['RES-RC-2026-1', 1],

            // Registry 2 — RES 1992-2026 (Pool Office) and SIT.
            'RES 1992 lower bound'  => ['RES-1992-15', 2],
            'RES post-1991'         => ['RES-2010-1', 2],
            'RES 2026 upper bound'  => ['RES-2026-1', 2],
            'SIT any year'          => ['SIT-2005-78', 2],
            'SIT lower bound 1981'  => ['SIT-1981-1', 2],

            // Registry 3 — all CON- variants.
            'CON-COM'               => ['CON-COM-2014-35', 3],
            'CON-RES'               => ['CON-RES-1990-1', 3],
            'CON-IND'               => ['CON-IND-2000-1', 3],
            'CON-AG'                => ['CON-AG-2000-1', 3],
            'CON-RES-RC'            => ['CON-RES-RC-2000-1', 3],
            'CON-COM-RC'            => ['CON-COM-RC-2000-1', 3],
            'CON-IND-RC'            => ['CON-IND-RC-2000-1', 3],
            'CON-AG-RC'             => ['CON-AG-RC-2023-15', 3],

            // Longest-prefix-first: CON-RES must not fall back to RES's range.
            'CON-RES not RES range' => ['CON-RES-1991-1', 3],

            // Out of scope / edge cases -> null.
            'Unknown prefix'        => ['XYZ-2020-1', null],
            'Different registry family (ST)' => ['ST-2020-1', null],
            'DCIV family'            => ['DCIV-2020-1', null],
            'KANGIS family'          => ['KNML-2020-1', null],
            'No year segment'        => ['RES-ABCD', null],
            'Empty string'           => ['', null],
            'Whitespace only'        => ['   ', null],
            'Year just outside RES-1'=> ['RES-1980-1', null],
        ];
    }

    public function test_detect_returns_structured_reason_for_prefix_outside_config(): void
    {
        $result = $this->detector->detect('ST-2020-1');
        $this->assertNull($result['registry']);
        $this->assertSame('prefix_not_in_registry_config', $result['reason']);
    }

    public function test_detect_returns_structured_reason_for_unparseable_number(): void
    {
        $result = $this->detector->detect('NOT-A-FILE-NUMBER');
        $this->assertNull($result['registry']);
        $this->assertSame('unparseable_no_year', $result['reason']);
    }

    public function test_detect_returns_structured_reason_for_empty_input(): void
    {
        $result = $this->detector->detect(null);
        $this->assertNull($result['registry']);
        $this->assertSame('empty_file_number', $result['reason']);
    }

    public function test_detect_matched_reason_includes_zone(): void
    {
        $result = $this->detector->detect('RES-1985-1');
        $this->assertSame(1, $result['registry']);
        $this->assertSame('matched', $result['reason']);
        $this->assertSame('archive', $result['zone']);
    }
}
