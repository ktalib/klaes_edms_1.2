<?php

namespace Tests\Unit\Support;

use App\Support\LgaNormalizer;
use Tests\TestCase;

/**
 * LgaNormalizer — folding free-text LGA values onto the canonical `lgas` table.
 *
 * The stakes: `file_index_cache` on a surveyor's handset is seeded by LGA, so a
 * value that fails to resolve means those files do not exist offline, silently.
 * Equally, a value resolved to the WRONG LGA files a record under the wrong
 * area — worse than leaving it unresolved. Both directions are asserted here.
 */
class LgaNormalizerTest extends TestCase
{
    /** The real canonical set, as at 2026-08-16. */
    private const CANONICAL = [
        'Ajingi', 'Albasu', 'Bagwai', 'Bebeji', 'Bichi', 'Bunkure', 'Dala',
        'Dambatta', 'Dawakin Kudu', 'Dawakin Tofa', 'Doguwa', 'Fagge',
        'Gabasawa', 'Garko', 'Garun Mallam', 'Gaya', 'Gezawa', 'Ghari', 'Gwale',
        'Gwarzo', 'Kabo', 'Kano Municipal', 'Karaye', 'Kibiya', 'Kiru',
        'Kumbotso', 'Kura', 'Madobi', 'Makoda', 'Minjibir', 'Nasarawa', 'Rano',
        'Rimin Gado', 'Rogo', 'Shanono', 'Sumaila', 'Takai', 'Tarauni', 'Tofa',
        'Tsanyawa', 'Tudun Wada', 'Ungogo', 'Unknown', 'Warawa', 'Wudil',
    ];

    private function normalize(?string $raw): ?string
    {
        return LgaNormalizer::normalize($raw, self::CANONICAL);
    }

    // -----------------------------------------------------------------------
    // Exact and near-exact matching
    // -----------------------------------------------------------------------

    public function test_canonical_values_pass_through_unchanged(): void
    {
        foreach (self::CANONICAL as $name) {
            $this->assertSame($name, $this->normalize($name));
        }
    }

    /** @dataProvider casingProvider */
    public function test_matching_ignores_case_and_spacing(string $raw, string $expected): void
    {
        $this->assertSame($expected, $this->normalize($raw));
    }

    public static function casingProvider(): array
    {
        return [
            'upper'          => ['KANO MUNICIPAL', 'Kano Municipal'],
            'lower'          => ['kano municipal', 'Kano Municipal'],
            'padded'         => ['  Ungogo  ', 'Ungogo'],
            'double space'   => ['KANO  MUNICIPAL', 'Kano Municipal'],
            'mixed'          => ['dAwAkIn KuDu', 'Dawakin Kudu'],
        ];
    }

    // -----------------------------------------------------------------------
    // The misspellings that actually cost us files
    // -----------------------------------------------------------------------

    /** @dataProvider realVariantProvider */
    public function test_known_variants_resolve(string $raw, string $expected): void
    {
        $this->assertSame($expected, $this->normalize($raw), "\"$raw\" should resolve to $expected");
    }

    /** Every case here was observed in live data, with its row count. */
    public static function realVariantProvider(): array
    {
        return [
            'NASSARAWA (3,388 files)' => ['NASSARAWA', 'Nasarawa'],
            'NASSARWA'                => ['NASSARWA', 'Nasarawa'],
            'MUNICIPAL (334)'         => ['MUNICIPAL', 'Kano Municipal'],
            'MUNINCIPAL (253)'        => ['MUNINCIPAL', 'Kano Municipal'],
            'KANO MUNICPAL'           => ['KANO MUNICPAL', 'Kano Municipal'],
            'KAN0 MUNICIPAL (zero)'   => ['KAN0 MUNICIPAL', 'Kano Municipal'],
            'D/KUDU (154)'            => ['D/KUDU', 'Dawakin Kudu'],
            'UNGOGGO (110)'           => ['UNGOGGO', 'Ungogo'],
            'DANBATTA (64)'           => ['DANBATTA', 'Dambatta'],
            'D/TOFA (56)'             => ['D/TOFA', 'Dawakin Tofa'],
            'KUMBTSO (22)'            => ['KUMBTSO', 'Kumbotso'],
            'T/WADA (16)'             => ['T/WADA', 'Tudun Wada'],
            'GARIN MALLAM'            => ['GARIN MALLAM', 'Garun Mallam'],
            'TARAUNA'                 => ['TARAUNA', 'Tarauni'],
            'MINGIBIR'                => ['MINGIBIR', 'Minjibir'],
            'Faffe'                   => ['Faffe', 'Fagge'],
        ];
    }

    // -----------------------------------------------------------------------
    // What must NOT be guessed
    // -----------------------------------------------------------------------

    /**
     * Resolving any of these would file a record under the wrong LGA, which is
     * worse than leaving it unresolved for a human to fix.
     *
     * @dataProvider mustNotResolveProvider
     */
    public function test_ambiguous_and_foreign_values_stay_unresolved(string $raw): void
    {
        $this->assertNull($this->normalize($raw), "\"$raw\" must not be guessed at");
    }

    public static function mustNotResolveProvider(): array
    {
        return [
            // Jigawa State LGAs that turn up in this Kano column
            'Hadejia'   => ['HADEJIA'],
            'Dutse'     => ['DUTSE'],
            'Ringim'    => ['RINGIM'],
            'Gumel'     => ['GUMEL'],
            'Kazaure'   => ['KAZAURE'],
            // Ogun State, somehow
            'Egbado'    => ['EGBADO SOUTH'],
            // Wards/quarters, not LGAs
            'Waje'      => ['WAJE'],
            'Sharada'   => ['SHARADA'],
            'Naibawa'   => ['NAIBAWA'],
            'Giginyu'   => ['GIGINYU'],
            'Yakasai'   => ['YAKASAI'],
            // Spans three LGAs — no single right answer
            'Kano City' => ['KANO CITY'],
            'K/CITY'    => ['K/CITY'],
            // Too vague
            'KANO'      => ['KANO'],
            'KANO STATE'=> ['KANO STATE'],
            // Junk
            'a date'    => ['29-12-1984'],
            'placeholder' => ['Select LGA'],
            'fragment'  => ['DA'],
            'empty'     => [''],
        ];
    }

    public function test_null_and_blank_are_unresolved(): void
    {
        $this->assertNull($this->normalize(null));
        $this->assertNull($this->normalize('   '));
    }

    /**
     * An alias must never resolve to a name absent from the reference table —
     * otherwise a change to `lgas` turns into a silent bad write.
     */
    public function test_alias_targets_must_exist_in_the_reference_table(): void
    {
        $this->assertNull(
            LgaNormalizer::normalize('NASSARAWA', ['Kano Municipal']),
            'Nasarawa is not in this canonical set, so the alias must not resolve.'
        );
    }

    public function test_every_alias_target_is_a_canonical_name(): void
    {
        foreach (LgaNormalizer::aliases() as $raw => $target) {
            $this->assertContains($target, self::CANONICAL, "Alias \"$raw\" points at a non-canonical LGA.");
        }
    }

    // -----------------------------------------------------------------------
    // variantsFor() — what widens the offline cache query
    // -----------------------------------------------------------------------

    public function test_variants_include_the_canonical_name_itself(): void
    {
        $this->assertContains('Nasarawa', LgaNormalizer::variantsFor('Nasarawa'));
    }

    public function test_variants_pick_up_the_misspellings(): void
    {
        $variants = LgaNormalizer::variantsFor('Nasarawa');

        // Without this the offline cache misses 3,388 files.
        $this->assertContains('nassarawa', $variants);

        $municipal = LgaNormalizer::variantsFor('Kano Municipal');
        $this->assertContains('munincipal', $municipal);
        $this->assertContains('municipal', $municipal);
    }

    public function test_variants_of_an_unaliased_lga_are_just_itself(): void
    {
        $this->assertSame(['Wudil'], LgaNormalizer::variantsFor('Wudil'));
    }

    public function test_variants_do_not_leak_across_lgas(): void
    {
        $this->assertNotContains('nassarawa', LgaNormalizer::variantsFor('Kano Municipal'));
    }
}
