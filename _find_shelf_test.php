<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$file = \App\Models\FileIndexing::where('shelf_location', '!=', null)
  ->where('shelf_location', '!=', '')
  ->select('id', 'file_number', 'shelf_location', 'file_title')
  ->first();

if ($file) {
  echo 'File Number: ' . $file->file_number . PHP_EOL;
  echo 'Shelf/Rack: ' . $file->shelf_location . PHP_EOL;
  echo 'Title: ' . $file->file_title . PHP_EOL;
} else {
  echo 'No files with shelf_location found' . PHP_EOL;
}
