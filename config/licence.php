<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Licence verification
    |--------------------------------------------------------------------------
    |
    | Desktop copies gate writes on an Ed25519-signed licence. The app carries
    | the vendor public key in code (App\Services\Licence\Licence), and the
    | private key never enters this repository. This override exists only so
    | the test suite can sign throwaway key pairs; the desktop launcher and the
    | server environment files never set it, so real installs always use the
    | embedded key.
    |
    */

    'public_key' => env('WYA_LICENCE_PUBLIC_KEY'),

];
