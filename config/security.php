<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Two-factor authentication for administrators
    |--------------------------------------------------------------------------
    |
    | When true, an administrator without confirmed two-factor authentication
    | is sent to the security settings page until it is set up. Leave false
    | until every administrator has enrolled, then switch it on in Railway
    | with REQUIRE_ADMIN_2FA=true.
    |
    */

    'require_admin_2fa' => (bool) env('REQUIRE_ADMIN_2FA', false),

];
