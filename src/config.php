<?php

use craft\helpers\App;

return [

    'auth0Congig' => [
        /*
        |--------------------------------------------------------------------------
        |   Your auth0 domain
        |--------------------------------------------------------------------------
        |   As set in the auth0 administration page
        |
        */
        'domain'        => App::env( 'AUTH0_DOMAIN' ),

        /*
        |--------------------------------------------------------------------------
        |   Your APP id
        |--------------------------------------------------------------------------
        |   As set in the auth0 administration page
        |
        */
        'client_id'     => App::env( 'AUTH0_CLIENT_ID' ),

        /*
        |--------------------------------------------------------------------------
        |   Your APP secret
        |--------------------------------------------------------------------------
        |   As set in the auth0 administration page
        |
        */
        'client_secret' => App::env( 'AUTH0_CLIENT_SECRET' ),

        /*
        |--------------------------------------------------------------------------
        |   The redirect URI
        |--------------------------------------------------------------------------
        |   Should be the same that the one configure in the route to handle the
        |   'Auth0\Login\Auth0Controller@callback'
        |
        */
        'redirect_uri'  => App::env( 'PRIMARY_SITE_URL' ) . '/auth0/callback',

        /*
        |--------------------------------------------------------------------------
        |   Persistence Configuration
        |--------------------------------------------------------------------------
        |   persist_user            (Boolean) Optional. Indicates if you want to persist the user info, default true
        |   persist_access_token    (Boolean) Optional. Indicates if you want to persist the access token, default false
        |   persist_refresh_token   (Boolean) Optional. Indicates if you want to persist the refresh token, default false
        |   persist_id_token        (Boolean) Optional. Indicates if you want to persist the id token, default false
        |
        */
        'persist_user' => true,
        'persist_access_token' => false,
        'persist_refresh_token' => false,
        'persist_id_token' => false,

        /*
        |--------------------------------------------------------------------------
        |   The authorized token issuers
        |--------------------------------------------------------------------------
        |   This is used to verify the decoded tokens when using RS256
        |
        */
        'authorized_issuers'  => [ App::env( 'AUTH0_DOMAIN' ) ],

        /*
        |--------------------------------------------------------------------------
        |   The authorized token audiences
        |--------------------------------------------------------------------------
        |
        */
        // 'api_identifier'  => '',

        /*
        |--------------------------------------------------------------------------
        |   The secret format
        |--------------------------------------------------------------------------
        |   Used to know if it should decode the secret when using HS256
        |
        */
        'secret_base64_encoded'  => false,

        /*
        |--------------------------------------------------------------------------
        |   Supported algorithms
        |--------------------------------------------------------------------------
        |   Token decoding algorithms supported by your API
        |
        */
        'supported_algs'        => [ 'RS256' ],

        /*
        |--------------------------------------------------------------------------
        |   Guzzle Options
        |--------------------------------------------------------------------------
        |   guzzle_options    (array) optional. Used to specify additional connection options e.g. proxy settings
        |
        */
        // 'guzzle_options' => []
    ],

    'auth0LoginParams' => [
        'background' => App::env('LOGIN_BG'),
        'logo' => App::env('APP_LOGO'),
        'buttonColor' => App::env('PRIMARY_COLOUR'),
        'appName' => App::env('APP_NAME'),
        'defaultConnection' => App::env('AUTH0_DB_CONNECTION'),
    ]
];
