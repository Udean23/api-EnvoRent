<?php

return [
    'api_key' => env('XENDIT_API_KEY'),
    'public_key' => env('XENDIT_PUBLIC_KEY'),
    'webhook_token' => env('XENDIT_WEBHOOK_TOKEN'),
    'is_production' => env('XENDIT_IS_PRODUCTION', false),
];
