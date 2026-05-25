<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'bigshop' => [
        'activation_secret' => env('BIGSHOP_ACTIVATION_SECRET'),
    ],

    'checkout' => [
        'default_provider' => env('CHECKOUT_PAYMENT_PROVIDER', 'mercado_pago'),
        'success_url' => env('CHECKOUT_SUCCESS_URL', env('FRONTEND_URL') ? rtrim(env('FRONTEND_URL'), '/').'/checkout/sucesso' : null),
        'cancel_url' => env('CHECKOUT_CANCEL_URL', env('FRONTEND_URL') ? rtrim(env('FRONTEND_URL'), '/').'/checkout' : null),
    ],

    'pagarme' => [
        'env' => env('PAGARME_ENV', 'sandbox'),
        'api_version' => env('PAGARME_API_VERSION', 'v5'),
        'base_url' => env(
            'PAGARME_BASE_URL',
            (env('PAGARME_ENV', 'sandbox') === 'sandbox' ? 'https://sdx-api.pagar.me/core/' : 'https://api.pagar.me/core/')
            .env('PAGARME_API_VERSION', 'v5')
        ),
        'secret_key' => env('PAGARME_SECRET_KEY'),
        'public_key' => env('PAGARME_PUBLIC_KEY'),
        'webhook_secret' => env('PAGARME_WEBHOOK_SECRET'),
        'checkout_success_url' => env('PAGARME_CHECKOUT_SUCCESS_URL'),
        'checkout_cancel_url' => env('PAGARME_CHECKOUT_CANCEL_URL'),
    ],

    'mercado_pago' => [
        'env' => env('MERCADO_PAGO_ENV', env('MERCADOPAGO_ENV', 'production')),
        'base_url' => env('MERCADO_PAGO_BASE_URL', 'https://api.mercadopago.com'),
        'public_key' => env('MERCADO_PAGO_PUBLIC_KEY', env('MERCADOPAGO_PUBLIC_KEY')),
        'access_token' => env('MERCADO_PAGO_ACCESS_TOKEN', env('MERCADOPAGO_ACCESS_TOKEN')),
        'webhook_secret' => env('MERCADO_PAGO_WEBHOOK_SECRET', env('MERCADOPAGO_SIGNING_SECRET')),
        'webhook_url' => env('MERCADO_PAGO_WEBHOOK_URL'),
        'checkout_success_url' => env('MERCADO_PAGO_CHECKOUT_SUCCESS_URL', env('CHECKOUT_SUCCESS_URL')),
        'checkout_cancel_url' => env('MERCADO_PAGO_CHECKOUT_CANCEL_URL', env('CHECKOUT_CANCEL_URL')),
    ],

    'ai' => [
        'provider' => env('AI_PROVIDER', 'local'),
        'model' => env('AI_MODEL', 'local-table-parser-v1'),
        'openai_api_key' => env('OPENAI_API_KEY'),
        'gemini_api_key' => env('GEMINI_API_KEY'),
    ],

];
