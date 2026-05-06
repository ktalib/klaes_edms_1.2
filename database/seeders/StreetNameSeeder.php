<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StreetNameSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $path = resource_path('views/components/StreetName.blade.php');
        
        if (file_exists($path)) {
            $content = file_get_contents($path);
            
            // Extract values from option tags
            preg_match_all('/<option value="([^"]+)">/', $content, $matches);
            
            if (!empty($matches[1])) {
                $streetNames = array_unique($matches[1]);
                $data = [];
                $now = now();
                
                foreach ($streetNames as $name) {
                    $name = trim($name);
                    if ($name !== '') {
                        $data[] = [
                            'name' => $name, 
                            'created_at' => $now, 
                            'updated_at' => $now
                        ];
                    }
                }
                
                // Insert in chunks to avoid memory issues
                foreach (array_chunk($data, 100) as $chunk) {
                    \App\Models\StreetName::insert($chunk);
                }
            }
        }
    }
}
