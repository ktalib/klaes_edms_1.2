<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Legacy edit form
    |--------------------------------------------------------------------------
    |
    | /fileindexing/{id}/edit renders the create form in update mode
    | (FileIndexUpdatePageController). Set this true to fall back to the old
    | resources/views/fileindexing/edit.blade.php screen for everyone.
    |
    | Note: .env changes do not survive a production code upload, so the
    | per-request escape hatch is the query parameter, which always works:
    |
    |     /fileindexing/{id}/edit?legacy=1
    |
    */

    'legacy_edit_form' => env('FILEINDEX_LEGACY_EDIT_FORM', false),

];
