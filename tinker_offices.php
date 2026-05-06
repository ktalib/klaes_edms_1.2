try {
    $rows = DB::connection('sqlsrv')->table('offices')
        ->whereIn('office_code', ['CS','VET','GEO','PROD','DG','KRE','CUST','VETTING','GIS'])
        ->orWhere('department','like','%Customer%')
        ->orWhere('department','like','%Vetting%')
        ->orWhere('department','like','%Geometry%')
        ->orWhere('department','like','%Production%')
        ->orWhere('department','like','%Collection%')
        ->orWhere('department','like','%Registry%')
        ->get(['office_code','office_name','department']);
    foreach($rows as $r){
        echo $r->office_code.' | '.$r->office_name.' | '.$r->department.PHP_EOL;
    }
    echo 'TOTAL: '.count($rows);
} catch(\Exception $e){
    echo 'Error: '.$e->getMessage();
}
