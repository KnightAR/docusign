<?php

return [

    /**
     * The DocuSign Integrator's Key
     */

    'integrator_key' => env('DOCUSIGN_INTEGRATOR_KEY'),

    /**
     * The Docusign Account Email
     */
    'email' => env('DOCUSIGN_ACCOUNT_EMAIL'),

    /**
     * The Docusign Account Password
     */
    'password' => env('DOCUSIGN_ACCOUNT_PW'),

    /**
     * The version of DocuSign API (Ex: v1, v2)
     */
    'version' => 'v2',

    /**
     * The DocuSign Environment (Ex: demo, test, www)
     */
    'environment' => env('DOCUSIGN_ENV', 'demo'),

    /**
     * The DocuSign Account Id
     */
    'account_id' => env('DOCUSIGN_ACCOUNT_ID'),

    /**
     * Authentication Method (Ex: user, jwt) Note: user auth is depreciated
     */
    'auth_method' => env('DOCUSIGN_AUTH_METHOD', 'jwt'),

    /**
     * oAuth Config
     */

    'OAUTH' => [
        'clientId' => env('DOCUSIGN_INTEGRATOR_KEY'),
        'clientSecret' => env('DOCUSIGN_INTEGRATOR_SECRET'),
        'authorizationServer' => 'https://' . env('DOCUSIGN_AUTHORIZATION_SERVER', 'account-d') . '.docusign.com',
        'allowSilentAuth' => env('DOCUSIGN_ALLOW_SILENT_AUTH', true)
    ],

    /**
     * JWT Config
     */

    'JWT' => [
        'ds_client_id' => env('DOCUSIGN_INTEGRATOR_KEY'), // The app's DocuSign integration key
        'authorization_server' => env('DOCUSIGN_AUTHORIZATION_SERVER', 'account-d') . '.docusign.com',
        "ds_impersonated_user_id" => env('DOCUSIGN_USER_ID'),  // the id of the user
        "private_key_file" => storage_path(env('DOCUSIGN_KEY_FILE', "docusign_jwt_private.key")), // path to private key file
    ],

    /**
     * Envelope Trait Configs
     */

    /**
     * Envelope ID field
     */
    'envelope_field' => 'envelopeId',

    /**
     * Recipient IDs to save tabs for upon creating the Envelope (false = Disabled)
     */
    'save_recipient_tabs' => [1],

    /**
     * Envelope Tabs field
     */
    'tabs_field' => 'envelopeTabs',

    /**
     * Envelope Documents field (false = Disabled)
     */
    'documents_field' => 'templateDocuments',
];
