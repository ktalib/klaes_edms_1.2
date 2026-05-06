<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Check remaining NULL records for partial party data
$partial = DB::connection('sqlsrv')->select("
    SELECT id, instrument_type,
        Grantor, Grantee, Assignor, Assignee, 
        Mortgagor, Mortgagee, Surrenderor, Surrenderee,
        Lessor, Lessee, Releasor, Releasee, Donor, Donee, Vendor, Purchaser
    FROM pic
    WHERE party_1 IS NULL AND party_2 IS NULL
    AND (
        (Grantee IS NOT NULL AND Grantee != '') OR
        (Assignee IS NOT NULL AND Assignee != '') OR
        (Mortgagee IS NOT NULL AND Mortgagee != '') OR
        (Surrenderee IS NOT NULL AND Surrenderee != '') OR
        (Lessee IS NOT NULL AND Lessee != '') OR
        (Releasee IS NOT NULL AND Releasee != '') OR
        (Donee IS NOT NULL AND Donee != '') OR
        (Purchaser IS NOT NULL AND Purchaser != '') OR
        (Grantor IS NOT NULL AND Grantor != '') OR
        (Assignor IS NOT NULL AND Assignor != '') OR
        (Mortgagor IS NOT NULL AND Mortgagor != '') OR
        (Surrenderor IS NOT NULL AND Surrenderor != '') OR
        (Lessor IS NOT NULL AND Lessor != '') OR
        (Releasor IS NOT NULL AND Releasor != '') OR
        (Donor IS NOT NULL AND Donor != '') OR
        (Vendor IS NOT NULL AND Vendor != '')
    )
");

echo "Records with party_1=NULL but some source data: " . count($partial) . "\n";
foreach ($partial as $r) {
    echo "  id={$r->id} type={$r->instrument_type}";
    if ($r->Grantor) echo " Grantor={$r->Grantor}";
    if ($r->Grantee) echo " Grantee={$r->Grantee}";
    if ($r->Assignor) echo " Assignor={$r->Assignor}";
    if ($r->Assignee) echo " Assignee={$r->Assignee}";
    echo "\n";
}
