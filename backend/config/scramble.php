<?php

use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;

return [
    // Document only the versioned application API; exclude Laravel's legacy /api/user route.
    'api_path' => 'api/v1',

    'info' => [
        'version' => env('API_VERSION', '1.0.0'),
        'description' => 'Wersjonowane REST API aplikacji Dziennik Podróży.',
    ],

    'ui' => [
        'title' => 'Dziennik Podróży API',
    ],

    'renderer' => 'swagger',

    'renderers' => [
        'swagger' => [
            'view' => 'swagger',
        ],
    ],

    // Sanctum personal access tokens are sent as HTTP Bearer tokens by mobile clients.
    'security_strategy' => MiddlewareAuthSecurityStrategy::class,
];
