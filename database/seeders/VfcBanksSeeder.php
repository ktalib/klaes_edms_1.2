<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VfcBanksSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $banks = [
            ['name' => '9mobile 9Payment Service Bank', 'code' => '120001', 'logo' => 'bank_logos/9mobile-9payment-service-bank-ng.png'],
            ['name' => 'Abbey Mortgage Bank', 'code' => '801', 'logo' => 'bank_logos/abbey-mortgage-bank.png'],
            ['name' => 'Above Only MFB', 'code' => '51204', 'logo' => 'bank_logos/above-only-mfb.png'],
            ['name' => 'Abulesoro MFB', 'code' => '51312', 'logo' => 'bank_logos/abulesoro-mfb-ng.png'],
            ['name' => 'Access Bank', 'code' => '044', 'logo' => 'bank_logos/access-bank.png'],
            ['name' => 'Access Bank (Diamond)', 'code' => '063', 'logo' => 'bank_logos/access-bank-diamond.png'],
            ['name' => 'Accion Microfinance Bank', 'code' => '602', 'logo' => 'bank_logos/accion-microfinance-bank-ng.png'],
            ['name' => 'Airtel Smartcash PSB', 'code' => '120004', 'logo' => 'bank_logos/airtel-smartcash-psb-ng.png'],
            ['name' => 'Amegy Microfinance Bank', 'code' => '090629', 'logo' => 'bank_logos/amegy-microfinance-bank-ng.png'],
            ['name' => 'Amju Unique MFB', 'code' => '50926', 'logo' => 'bank_logos/amju-unique-mfb.png'],
            ['name' => 'AMPERSAND MICROFINANCE BANK', 'code' => '51341', 'logo' => 'bank_logos/ampersand-microfinance-bank-ng.png'],
            ['name' => 'Aramoko MFB', 'code' => '50083', 'logo' => 'bank_logos/aramoko-mfb.png'],
            ['name' => 'Astrapolaris MFB LTD', 'code' => 'MFB50094', 'logo' => 'bank_logos/astrapolaris-mfb.png'],
            ['name' => 'Bainescredit MFB', 'code' => '51229', 'logo' => 'bank_logos/bainescredit-mfb.png'],
            ['name' => 'Bowen Microfinance Bank', 'code' => '50931', 'logo' => 'bank_logos/bowen-microfinance-bank.png'],
            ['name' => 'Branch International Financial Services Limited', 'code' => 'FC40163', 'logo' => 'bank_logos/branch.png'],
            ['name' => 'Carbon', 'code' => '565', 'logo' => 'bank_logos/carbon.png'],
            ['name' => 'CASHCONNECT MFB', 'code' => '865', 'logo' => 'bank_logos/cashconnect-mfb-ng.png'],
            ['name' => 'CEMCS Microfinance Bank', 'code' => '50823', 'logo' => 'bank_logos/cemcs-microfinance-bank.png'],
            ['name' => 'Chanelle Microfinance Bank Limited', 'code' => '50171', 'logo' => 'bank_logos/chanelle-microfinance-bank-limited-ng.png'],
            ['name' => 'Chikum Microfinance bank', 'code' => '312', 'logo' => 'bank_logos/chikum-microfinance-bank-ng.png'],
            ['name' => 'Consumer Microfinance Bank', 'code' => '50910', 'logo' => 'bank_logos/consumer-microfinance-bank-ng.png'],
            ['name' => 'Corestep MFB', 'code' => '50204', 'logo' => 'bank_logos/corestep-mfb.png'],
            ['name' => 'Coronation Merchant Bank', 'code' => '559', 'logo' => 'bank_logos/coronation-merchant-bank-ng.png'],
            ['name' => 'Dot Microfinance Bank', 'code' => '50162', 'logo' => 'bank_logos/dot-microfinance-bank-ng.png'],
            ['name' => 'Ecobank Nigeria', 'code' => '050', 'logo' => 'bank_logos/ecobank-nigeria.png'],
            ['name' => 'Ekimogun MFB', 'code' => '50263', 'logo' => 'bank_logos/ekimogun-mfb-ng.png'],
            ['name' => 'Ekondo Microfinance Bank', 'code' => '098', 'logo' => 'bank_logos/ekondo-microfinance-bank-ng.png'],
            ['name' => 'Eyowo', 'code' => '50126', 'logo' => 'bank_logos/eyowo.png'],
            ['name' => 'Fidelity Bank', 'code' => '070', 'logo' => 'bank_logos/fidelity-bank.png'],
            ['name' => 'Firmus MFB', 'code' => '51314', 'logo' => 'bank_logos/firmus-mfb.png'],
            ['name' => 'First Bank of Nigeria', 'code' => '011', 'logo' => 'bank_logos/first-bank-of-nigeria.png'],
            ['name' => 'First City Monument Bank', 'code' => '214', 'logo' => 'bank_logos/first-city-monument-bank.png'],
            ['name' => 'FirstTrust Mortgage Bank Nigeria', 'code' => '413', 'logo' => 'bank_logos/firsttrust-mortgage-bank-nigeria-ng.png'],
            ['name' => 'FLOURISH MFB', 'code' => '50315', 'logo' => 'bank_logos/flourish-mfb-ng.png'],
            ['name' => 'Gateway Mortgage Bank LTD', 'code' => '812', 'logo' => 'bank_logos/gateway-mortgage-bank.png'],
            ['name' => 'Globus Bank', 'code' => '00103', 'logo' => 'bank_logos/globus-bank.png'],
            ['name' => 'GoMoney', 'code' => '100022', 'logo' => 'bank_logos/gomoney.png'],
            ['name' => 'Goodnews Microfinance Bank', 'code' => '50739', 'logo' => 'bank_logos/goodnews-microfinance-bank-ng.png'],
            ['name' => 'Guaranty Trust Bank', 'code' => '058', 'logo' => 'bank_logos/guaranty-trust-bank.png'],
            ['name' => 'Hasal Microfinance Bank', 'code' => '50383', 'logo' => 'bank_logos/hasal-microfinance-bank.png'],
            ['name' => 'Heritage Bank', 'code' => '030', 'logo' => 'bank_logos/heritage-bank.png'],
            ['name' => 'HopePSB', 'code' => '120002', 'logo' => 'bank_logos/hopepsb-ng.png'],
            ['name' => 'Ibile Microfinance Bank', 'code' => '51244', 'logo' => 'bank_logos/ibile-mfb.png'],
            ['name' => 'Ikoyi Osun MFB', 'code' => '50439', 'logo' => 'bank_logos/ikoyi-osun-mfb.png'],
            ['name' => 'Ilaro Poly Microfinance Bank', 'code' => '50442', 'logo' => 'bank_logos/ilaro-poly-microfinance-bank-ng.png'],
            ['name' => 'Imowo MFB', 'code' => '50453', 'logo' => 'bank_logos/imowo-mfb-ng.png'],
            ['name' => 'Infinity MFB', 'code' => '50457', 'logo' => 'bank_logos/infinity-mfb.png'],
            ['name' => 'Jaiz Bank', 'code' => '301', 'logo' => 'bank_logos/jaiz-bank.png'],
            ['name' => 'Kadpoly MFB', 'code' => '50502', 'logo' => 'bank_logos/kadpoly-mfb.png'],
            ['name' => 'Keystone Bank', 'code' => '082', 'logo' => 'bank_logos/keystone-bank.png'],
            ['name' => 'Kredi Money MFB LTD', 'code' => '50200', 'logo' => 'bank_logos/kredi-money-mfb.png'],
            ['name' => 'Kuda Bank', 'code' => '50211', 'logo' => 'bank_logos/kuda-bank.png'],
            ['name' => 'Lagos Building Investment Company Plc.', 'code' => '90052', 'logo' => 'bank_logos/lbic-plc.png'],
            ['name' => 'Links MFB', 'code' => '50549', 'logo' => 'bank_logos/links-mfb.png'],
            ['name' => 'Living Trust Mortgage Bank', 'code' => '031', 'logo' => 'bank_logos/living-trust-mortgage-bank.png'],
            ['name' => 'Lotus Bank', 'code' => '303', 'logo' => 'bank_logos/lotus-bank.png'],
            ['name' => 'Mint MFB', 'code' => '50304', 'logo' => 'bank_logos/mint-mfb.png'],
            ['name' => 'Moniepoint MFB', 'code' => '50515', 'logo' => 'bank_logos/moniepoint-mfb-ng.png'],
            ['name' => 'MTN Momo PSB', 'code' => '120003', 'logo' => 'bank_logos/mtn-momo-psb-ng.png'],
            ['name' => 'Optimus Bank Limited', 'code' => '107', 'logo' => 'bank_logos/optimus-bank-ltd.png'],
            ['name' => 'Parallex Bank', 'code' => '104', 'logo' => 'bank_logos/parallex-bank.png'],
            ['name' => 'Parkway - ReadyCash', 'code' => '311', 'logo' => 'bank_logos/parkway-ready-cash.png'],
            ['name' => 'Peace Microfinance Bank', 'code' => '50743', 'logo' => 'bank_logos/peace-microfinance-bank-ng.png'],
            ['name' => 'Platinum Mortgage Bank', 'code' => '268', 'logo' => 'bank_logos/platinum-mortgage-bank-ng.png'],
            ['name' => 'Polaris Bank', 'code' => '076', 'logo' => 'bank_logos/polaris-bank.png'],
            ['name' => 'Polyunwana MFB', 'code' => '50864', 'logo' => 'bank_logos/polyunwana-mfb-ng.png'],
            ['name' => 'PremiumTrust Bank', 'code' => '105', 'logo' => 'bank_logos/premiumtrust-bank-ng.png'],
            ['name' => 'Providus Bank', 'code' => '101', 'logo' => 'bank_logos/providus-bank.png'],
            ['name' => 'QuickFund MFB', 'code' => '51293', 'logo' => 'bank_logos/quickfund-mfb.png'],
            ['name' => 'Rand Merchant Bank', 'code' => '502', 'logo' => 'bank_logos/rand-merchant-bank.png'],
            ['name' => 'Refuge Mortgage Bank', 'code' => '90067', 'logo' => 'bank_logos/refuge-mortgage-bank.png'],
            ['name' => 'ROCKSHIELD MICROFINANCE BANK', 'code' => '50767', 'logo' => 'bank_logos/rockshield-microfinance-bank-ng.png'],
            ['name' => 'Rubies MFB', 'code' => '125', 'logo' => 'bank_logos/rubies-mfb.png'],
            ['name' => 'Safe Haven MFB', 'code' => '51113', 'logo' => 'bank_logos/safe-haven-mfb-ng.png'],
            ['name' => 'Safe Haven Microfinance Bank Limited', 'code' => '951113', 'logo' => 'bank_logos/safe-haven-microfinance-bank-limited-ng.png'],
            ['name' => 'SAGE GREY FINANCE LIMITED', 'code' => '40165', 'logo' => 'bank_logos/sage-grey-finance-limited-ng.png'],
            ['name' => 'Shield MFB', 'code' => '50582', 'logo' => 'bank_logos/shield-mfb-ng.png'],
            ['name' => 'Solid Allianze MFB', 'code' => '51062', 'logo' => 'bank_logos/solid-allianze-mfb.png'],
            ['name' => 'Solid Rock MFB', 'code' => '50800', 'logo' => 'bank_logos/solid-rock-mfb.png'],
            ['name' => 'Sparkle Microfinance Bank', 'code' => '51310', 'logo' => 'bank_logos/sparkle-microfinance-bank.png'],
            ['name' => 'Stanbic IBTC Bank', 'code' => '221', 'logo' => 'bank_logos/stanbic-ibtc-bank.png'],
            ['name' => 'Standard Chartered Bank', 'code' => '068', 'logo' => 'bank_logos/standard-chartered-bank.png'],
            ['name' => 'Stellas MFB', 'code' => '51253', 'logo' => 'bank_logos/stellas-mfb.png'],
            ['name' => 'Sterling Bank', 'code' => '232', 'logo' => 'bank_logos/sterling-bank.png'],
            ['name' => 'Suntrust Bank', 'code' => '100', 'logo' => 'bank_logos/suntrust-bank.png'],
            ['name' => 'TAJ Bank', 'code' => '302', 'logo' => 'bank_logos/taj-bank.png'],
            ['name' => 'Tangerine Money', 'code' => '51269', 'logo' => 'bank_logos/tangerine-money.png'],
            ['name' => 'TCF MFB', 'code' => '51211', 'logo' => 'bank_logos/tcf-mfb.png'],
            ['name' => 'Titan Paystack', 'code' => '100039', 'logo' => 'bank_logos/titan-paystack.png'],
            ['name' => 'U&C Microfinance Bank Ltd (U AND C MFB)', 'code' => '50840', 'logo' => 'bank_logos/uc-microfinance-bank-ltd-u-and-c-mfb-ng.png'],
            ['name' => 'Uhuru MFB', 'code' => 'MFB51322', 'logo' => 'bank_logos/uhuru-mfb-ng.png'],
            ['name' => 'Unical MFB', 'code' => '50871', 'logo' => 'bank_logos/unical-mfb.png'],
            ['name' => 'Unilag Microfinance Bank', 'code' => '51316', 'logo' => 'bank_logos/unilag-microfinance-bank-ng.png'],
            ['name' => 'Union Bank of Nigeria', 'code' => '032', 'logo' => 'bank_logos/union-bank-of-nigeria.png'],
            ['name' => 'United Bank For Africa', 'code' => '033', 'logo' => 'bank_logos/united-bank-for-africa.png'],
            ['name' => 'Unity Bank', 'code' => '215', 'logo' => 'bank_logos/unity-bank.png'],
            ['name' => 'VFD Microfinance Bank Limited', 'code' => '566', 'logo' => 'bank_logos/vfd.png'],
            ['name' => 'Waya Microfinance Bank', 'code' => '51355', 'logo' => 'bank_logos/waya-microfinance-bank-ng.png'],
            ['name' => 'Zenith Bank', 'code' => '057', 'logo' => 'bank_logos/zenith-bank.png'],
        ];

        foreach ($banks as $bank) {
            DB::connection('sqlsrv')->table('vfc_banks')->updateOrInsert(
                ['code' => $bank['code']],
                [
                    'name' => $bank['name'],
                    'slug' => Str::slug($bank['name']),
                    'logo' => $bank['logo'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
