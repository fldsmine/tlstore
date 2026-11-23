<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backoffice URL Path
    |--------------------------------------------------------------------------
    |
    | The URL prefix the admin backoffice is served under. Change the
    | BACKOFFICE_PATH env value to move (or obscure) the backoffice without
    | touching code — it is read in bootstrap/app.php when registering
    | routes/backoffice.php. This must never overlap /, /web/*, /api/* or
    | /payments/*; the Flutter app and the public site never see it.
    |
    */

    'path' => env('BACKOFFICE_PATH', 'backoffice'),

    /*
    |--------------------------------------------------------------------------
    | Backoffice Name
    |--------------------------------------------------------------------------
    */

    'name' => env('BACKOFFICE_NAME', 'TrustLens Admin'),

];
