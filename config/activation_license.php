<?php

return [
    'api_username' => env('API_USERNAME'),
    'api_password' => env('API_PASSWORD'),
    'code_prefix' => env('ACTIVATION_LICENSE_CODE_PREFIX', 'STELLAR'),
    'max_batch_size' => (int) env('ACTIVATION_LICENSE_MAX_BATCH_SIZE', 100),
];
