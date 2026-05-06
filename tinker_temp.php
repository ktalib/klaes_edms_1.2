try {
    $c = app(\App\Http\Controllers\Api\FileTrackerApiController::class);
    $r = $c->dashboard();
    echo $r->getContent();
} catch(\Exception $e) {
    echo 'ERROR: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine();
}
