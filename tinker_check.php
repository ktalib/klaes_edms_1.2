<?php
try {
    $cols = array_map('strtolower', \Illuminate\Support\Facades\Schema::connection('sqlsrv')->getColumnListing('file_indexings'));
    $check = ['kangis_file_type','mls_file_no','kangis_file_no','new_kangis_file_no','kn_grouping_id'];
    foreach($check as $c) {
        echo $c.': '.(in_array($c,$cols)?'EXISTS':'MISSING').PHP_EOL;
    }
} catch(\Exception $e) {
    echo 'Error: '.$e->getMessage();
}
