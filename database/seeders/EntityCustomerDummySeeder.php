<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Entity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EntityCustomerDummySeeder extends Seeder
{
    /**
     * Seed the entities and customers tables with useful demo data.
     */
    public function run(): void
    {
        $records = [
            [
                'land_use_code' => 'RES',
                'entity' => [
                    'entity_type' => 'Individual',
                    'entity_name' => 'Amaka Oladipo',
                ],
                'customer' => [
                    'customer_type' => 'Individual',
                    'customer_name' => 'Amaka Oladipo',
                    'email' => 'amaka.olabido@example.com',
                    'phone' => '08010001001',
                    'status' => 'Active',
                    'reason_retired' => null,
                    'property_address' => 'Plot 12, Block B, Lekki Phase 1, Lagos',
                    'physical_address' => '12 Admiralty Way, Lekki Phase 1, Lagos',
                    'residential_address' => '12 Admiralty Way, Lekki Phase 1, Lagos',
                    'notes' => 'Prefers SMS notifications for updates.',
                ],
            ],
            [
                'land_use_code' => 'COM',
                'entity' => [
                    'entity_type' => 'Corporate',
                    'entity_name' => 'Suncrest Holdings Ltd',
                ],
                'customer' => [
                    'customer_type' => 'Corporate',
                    'customer_name' => 'Suncrest Holdings Ltd',
                    'email' => 'info@suncrestholdings.com',
                    'phone' => '08010001002',
                    'status' => 'Active',
                    'reason_retired' => null,
                    'property_address' => 'KM 14 Lekki-Epe Expressway, Lagos',
                    'physical_address' => '22 Admiralty Corporate Towers, Lagos',
                    'residential_address' => '22 Admiralty Corporate Towers, Lagos',
                    'notes' => 'Primary corporate client for mixed-use estates.',
                ],
            ],
            [
                'land_use_code' => 'MIX',
                'entity' => [
                    'entity_type' => 'Multiple Owners',
                    'entity_name' => 'Ajayi Family Estate',
                ],
                'customer' => [
                    'customer_type' => 'Multiple Owners',
                    'customer_name' => 'Ajayi Family Estate',
                    'email' => 'estate@ajayifamily.org',
                    'phone' => '08010001003',
                    'status' => 'Retired',
                    'reason_retired' => 'Merged into consolidated SPV in 2024.',
                    'property_address' => 'Farm Settlement Road, Abeokuta',
                    'physical_address' => 'Plot 4, Oke-Mosan Layout, Abeokuta',
                    'residential_address' => 'Plot 4, Oke-Mosan Layout, Abeokuta',
                    'notes' => 'Legacy record retained for reference only.',
                ],
            ],
            [
                'land_use_code' => 'RES',
                'entity' => [
                    'entity_type' => 'Individual',
                    'entity_name' => 'Chinedu Eze',
                ],
                'customer' => [
                    'customer_type' => 'Individual',
                    'customer_name' => 'Chinedu Eze',
                    'email' => 'chinedu.eze@example.com',
                    'phone' => '08010001004',
                    'status' => 'Active',
                    'reason_retired' => null,
                    'property_address' => 'Plot 8, Trans Amadi Garden, Port Harcourt',
                    'physical_address' => '8 Oil Mill Road, Port Harcourt',
                    'residential_address' => '8 Oil Mill Road, Port Harcourt',
                    'notes' => 'Owns multiple survey plans within Rivers.',
                ],
            ],
            [
                'land_use_code' => 'COM',
                'entity' => [
                    'entity_type' => 'Corporate',
                    'entity_name' => 'HarborPoint Logistics',
                ],
                'customer' => [
                    'customer_type' => 'Corporate',
                    'customer_name' => 'HarborPoint Logistics',
                    'email' => 'legal@harborpointlogistics.com',
                    'phone' => '08010001005',
                    'status' => 'Pending',
                    'reason_retired' => null,
                    'property_address' => 'Warehouse 3, Port Access Road, Onne',
                    'physical_address' => '1 Dockyard Lane, Onne',
                    'residential_address' => '1 Dockyard Lane, Onne',
                    'notes' => 'Awaiting compliance documents for onboarding.',
                ],
            ],
            [
                'land_use_code' => 'MIX',
                'entity' => [
                    'entity_type' => 'Multiple Owners',
                    'entity_name' => 'Unity Cooperative Group',
                ],
                'customer' => [
                    'customer_type' => 'Multiple Owners',
                    'customer_name' => 'Unity Cooperative Group',
                    'email' => 'office@unitycooperative.ng',
                    'phone' => '08010001006',
                    'status' => 'Active',
                    'reason_retired' => null,
                    'property_address' => 'Community Plot 5, Kubwa Abuja',
                    'physical_address' => '55 Independence Avenue, Abuja',
                    'residential_address' => '55 Independence Avenue, Abuja',
                    'notes' => 'Handles bulk land allocations for members.',
                ],
            ],
            [
                'land_use_code' => 'RES',
                'entity' => [
                    'entity_type' => 'Individual',
                    'entity_name' => 'Fatima Bello',
                ],
                'customer' => [
                    'customer_type' => 'Individual',
                    'customer_name' => 'Fatima Bello',
                    'email' => 'fatima.bello@example.com',
                    'phone' => '08010001007',
                    'status' => 'Active',
                    'reason_retired' => null,
                    'property_address' => 'Plot 19, Maitama Extension, Abuja',
                    'physical_address' => '19 Gana Street, Maitama, Abuja',
                    'residential_address' => '19 Gana Street, Maitama, Abuja',
                    'notes' => 'Key stakeholder for premium residential lots.',
                ],
            ],
            [
                'land_use_code' => 'IND',
                'entity' => [
                    'entity_type' => 'Corporate',
                    'entity_name' => 'Northwind Manufacturing PLC',
                ],
                'customer' => [
                    'customer_type' => 'Corporate',
                    'customer_name' => 'Northwind Manufacturing PLC',
                    'email' => 'compliance@northwindmfg.com',
                    'phone' => '08010001008',
                    'status' => 'Retired',
                    'reason_retired' => 'Company wound down operations in 2023.',
                    'property_address' => 'Industrial Plot 3, Kaduna South',
                    'physical_address' => '45 Station Road, Kaduna',
                    'residential_address' => '45 Station Road, Kaduna',
                    'notes' => 'Documents retained for archival lookups.',
                ],
            ],
            [
                'land_use_code' => 'RES',
                'entity' => [
                    'entity_type' => 'Individual',
                    'entity_name' => 'Samuel Dangana',
                ],
                'customer' => [
                    'customer_type' => 'Individual',
                    'customer_name' => 'Samuel Dangana',
                    'email' => 'samuel.dangana@example.com',
                    'phone' => '08010001009',
                    'status' => 'Pending',
                    'reason_retired' => null,
                    'property_address' => 'Plot 7, Rayfield Estate, Jos',
                    'physical_address' => '7 Crescent Lane, Rayfield, Jos',
                    'residential_address' => '7 Crescent Lane, Rayfield, Jos',
                    'notes' => 'Awaiting submission of site inspection photos.',
                ],
            ],
            [
                'land_use_code' => 'MIX',
                'entity' => [
                    'entity_type' => 'Multiple Owners',
                    'entity_name' => 'Kwara Joint Owners Consortium',
                ],
                'customer' => [
                    'customer_type' => 'Multiple Owners',
                    'customer_name' => 'Kwara Joint Owners Consortium',
                    'email' => 'support@kwarajointowners.org',
                    'phone' => '08010001010',
                    'status' => 'Active',
                    'reason_retired' => null,
                    'property_address' => 'Agbamu-Obbo Road, Kwara State',
                    'physical_address' => '12 Unity Close, Ilorin',
                    'residential_address' => '12 Unity Close, Ilorin',
                    'notes' => 'Coordinates agricultural lease agreements.',
                ],
            ],
        ];

        $serialCounters = [];

        DB::connection('sqlsrv')->transaction(function () use ($records, &$serialCounters) {
            foreach ($records as $index => $record) {
                $landUseCode = strtoupper($record['land_use_code'] ?? 'RES');
                $fileNumber = $this->generateFileNumber($landUseCode, $serialCounters);
                $accountNumber = $this->generateAccountNumber($index + 1);

                $entityAttributes = [
                    'entity_name' => $record['entity']['entity_name'],
                    'entity_type' => $record['entity']['entity_type'],
                ];

                $entityPayload = array_merge(
                    $record['entity'],
                    [
                        'file_number' => $fileNumber,
                    ]
                );

                $entity = Entity::updateOrCreate($entityAttributes, $entityPayload);

                $customerDefaults = [
                    'entity_id' => $entity->id,
                    'file_number' => $fileNumber,
                    'account_no' => $accountNumber,
                    'created_by' => $record['customer']['created_by'] ?? null,
                    'updated_by' => $record['customer']['updated_by'] ?? null,
                ];

                $customerData = array_merge($customerDefaults, $record['customer']);

                if (empty($customerData['residential_address'])) {
                    $customerData['residential_address'] = $customerData['physical_address'] ?? $customerData['property_address'];
                }

                Customer::updateOrCreate(
                    [
                        'customer_name' => $customerData['customer_name'],
                        'entity_id' => $entity->id,
                    ],
                    $customerData
                );
            }
        });
    }

    /**
     * Generate a land-use aware file number (e.g. COM-2012-33).
     */
    protected function generateFileNumber(string $landUseCode, array &$serialCounters): string
    {
        $landUseCode = strtoupper($landUseCode);
        $serialCounters[$landUseCode] = ($serialCounters[$landUseCode] ?? 0) + 1;
        $year = random_int(2012, (int) now()->year);
        $serial = str_pad((string) $serialCounters[$landUseCode], 2, '0', STR_PAD_LEFT);

        return sprintf('%s-%d-%s', $landUseCode, $year, $serial);
    }

    /**
     * Generate a sequential account number for demo data.
     */
    protected function generateAccountNumber(int $sequence): string
    {
        return sprintf('ACC-%d-%03d', (int) now()->year, $sequence);
    }
}
