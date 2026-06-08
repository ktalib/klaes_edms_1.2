<?php

namespace Database\Seeders;

use App\Models\Phs\PhsInstitution;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PhsDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::connection('sqlsrv')->transaction(function () {
            $lawFirm = $this->seedInstitution([
                'name' => 'Musa Chambers',
                'type' => 'law_firm',
                'email' => 'phs.demo@klaes.test',
                'phone' => '08030000000',
                'member_name' => 'Musa Sani',
                'department' => 'Search Desk',
            ]);

            $bank = $this->seedInstitution([
                'name' => 'Musa Trust Bank',
                'type' => 'bank',
                'email' => 'phs.bank.demo@klaes.test',
                'phone' => '08030000001',
                'member_name' => 'Musa Yakubu',
                'department' => 'Credit Risk',
            ]);

            $this->seedInvoices($lawFirm, [
                ['reference_no' => 'DEMO-INV-LAW-001', 'package' => 'professional'],
                ['reference_no' => 'DEMO-INV-LAW-002', 'package' => 'enterprise'],
            ]);

            $this->seedInvoices($bank, [
                ['reference_no' => 'DEMO-INV-BANK-001', 'package' => 'starter'],
                ['reference_no' => 'DEMO-INV-BANK-002', 'package' => 'professional'],
            ]);
        });
    }

    private function seedInstitution(array $data): PhsInstitution
    {
        $institution = PhsInstitution::firstOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'type' => $data['type'],
                'phone' => $data['phone'],
                'token_balance' => 0,
                'status' => 'active',
            ]
        );

        $member = $institution->members()->firstOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['member_name'],
                'password' => Hash::make('password'),
                'job_title' => 'Administrator',
                'department' => $data['department'],
                'user_type' => 'super_admin',
                'access_role' => 'analytics_viewer',
                'status' => 'active',
            ]
        );

        if ((int) $institution->token_balance < 100) {
            $institution->addTokens(100, 'bonus', [
                'reference_no' => 'DEMO-BONUS-' . strtoupper($data['type']),
                'notes' => 'Demo seed tokens',
            ], $member->id);
        }

        return $institution->fresh();
    }

    private function seedInvoices(PhsInstitution $institution, array $invoices): void
    {
        $packages = \App\Http\Controllers\Phs\PhsTokenController::packages();

        foreach ($invoices as $invoice) {
            $pkg = $packages[$invoice['package']];
            $institution->transactions()->firstOrCreate(
                ['reference_no' => $invoice['reference_no']],
                [
                    'type' => 'purchase',
                    'tokens' => $pkg['tokens'],
                    'balance_after' => (int) $institution->token_balance,
                    'package_name' => $pkg['name'],
                    'amount' => $pkg['price'],
                    'payment_method' => 'invoice',
                    'status' => 'pending',
                    'notes' => 'Demo invoice request awaiting KLAES approval',
                ]
            );
        }
    }
}
