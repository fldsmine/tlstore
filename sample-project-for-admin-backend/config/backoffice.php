<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backoffice URL Path
    |--------------------------------------------------------------------------
    |
    | The URL prefix the backoffice (admin panel) is served under. Change the
    | BACKOFFICE_PATH env value to move (or obscure) the backoffice without
    | touching code — it is read in bootstrap/app.php when registering
    | routes/backoffice.php.
    |
    */

    'path' => env('BACKOFFICE_PATH', 'backoffice'),

    /*
    |--------------------------------------------------------------------------
    | Backoffice Name
    |--------------------------------------------------------------------------
    */

    'name' => env('BACKOFFICE_NAME', 'Backoffice'),

];
