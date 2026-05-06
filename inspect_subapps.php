<?php

use Illuminate\Support\Facades\Schema;

echo "Table: subapplications\n";
$columns = Schema::getColumnListing('subapplications');
print_r($columns);
