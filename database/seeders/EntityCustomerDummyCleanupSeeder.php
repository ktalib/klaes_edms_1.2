<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Entity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EntityCustomerDummyCleanupSeeder extends Seeder
{
    /**
     * Remove the dummy entity/customer pairs inserted by EntityCustomerDummySeeder.
     */
    public function run(): void
    {
        $names = [
            'Amaka Oladipo',
            'Suncrest Holdings Ltd',
            'Ajayi Family Estate',
            'Chinedu Eze',
            'HarborPoint Logistics',
            'Unity Cooperative Group',
            'Fatima Bello',
            'Northwind Manufacturing PLC',
            'Samuel Dangana',
            'Kwara Joint Owners Consortium',
        ];

        DB::connection('sqlsrv')->transaction(function () use ($names) {
            Customer::withTrashed()->whereIn('customer_name', $names)->forceDelete();
            Entity::whereIn('entity_name', $names)->delete();
        });
    }
}
