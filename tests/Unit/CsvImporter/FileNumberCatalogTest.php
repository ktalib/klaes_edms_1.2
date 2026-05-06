<?php

namespace Tests\Unit\CsvImporter;

use App\Services\CsvImporter\Support\FileNumberCatalog;
use Tests\TestCase;

class FileNumberCatalogTest extends TestCase
{
    public function test_catalog_matches_known_category(): void
    {
        $catalog = new FileNumberCatalog(
            base_path('folder_watcher/static/correct_fileno.json')
        );

        $data = $catalog->getCatalog(true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('categories', $data);

        $match = $catalog->matchFileNumber('RES-1985-5000');

        $this->assertNotNull($match);
        $this->assertSame('RES', $match['code']);
    }
}
