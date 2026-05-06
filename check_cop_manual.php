<?php
require __DIR__.'/vendor/autoload.php';
\ = require __DIR__.'/bootstrap/app.php';
\ = \->make(Illuminate\Contracts\Console\Kernel::class);
\->bootstrap();

use Illuminate\Support\Facades\DB;

\ = 'CON-AG-2020-140';
\ = 'CON-COM-2026-227';

echo " Checking old file: \\n\;
\ = DB::connection('sqlsrv')->table('fileNumber')->where('mlsfNo', \)->first();
\ = DB::connection('sqlsrv')->table('file_indexings')->where('file_number', \)->first();

echo \Old fileNumber: \ . (\ ? json_encode(\) : 'None') . \\n\;
echo \Old file_indexings: \ . (\ ? json_encode(\) : 'None') . \\n\;

echo \Checking new file: \\n\;
\ = DB::connection('sqlsrv')->table('fileNumber')->where('mlsfNo', \)->first();
\ = DB::connection('sqlsrv')->table('file_indexings')->where('file_number', \)->first();

echo \New fileNumber: \ . (\ ? json_encode(\) : 'None') . \\n\;
echo \New file_indexings: \ . (\ ? json_encode(\) : 'None') . \\n\;
