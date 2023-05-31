<?php

return [
    // static path => controller as value
    '/' => 'Index',
    'GET /api/contracts' => 'Api\\Contracts',
    'GET /--api--/--contracts--/-- text/html' => 'Api\\Contracts',

    // path with params => controller as value (default filter)
    'DELETE /api/contracts/{id}' => 'Api\\Contracts\\Id',
    'GET /api/users--up/{id}/some.controller' => 'Api\\UsersUp\\Id\\SomeController',
    'GET /{o}.{n}.{c}.{e}.../--users-{id}-down--/--{ver}.controller--' => 'ONCE\\UsersIdDown\\VerController',

    'GET /{ver}/products' => 'ProductsController',
    'GET /{ver}/products/{id}' => 'ProductsController',
    'POST /{ver}/products/{id}' => 'ProductsController',
    'PUT /{ver}/products/{id}' => 'ProductsController',
    'PATCH /{ver}/products/{id}' => 'ProductsController',
    'DELETE /{ver}/products/{id}' => 'ProductsController',

    'GET /{ver}/users/{id}' => 'UsersController',

    // path with params => controller and filters in array
    'GET /v{ver}/orders/{id}' => [
        'controller' => 'OrdersController',
        'filters' => [
            'id' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 1]],
        ],
    ],

    // path with params => filters with regexp
    'GET /users/{uuid}' => [
        'controller' => 'UsersUuidController',
        'filters' => [
            'uuid' => [
                'filter' => FILTER_VALIDATE_REGEXP,
                'options' => [
                    'regexp' => '/^[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}$/',
                ],
            ],
        ],
    ],

    // MIME-TYPE empty vs set
    'POST /v{ver}/orders/' => [
        'controller' => 'OrdersController',
        'filters' => ['ver' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY]],
    ],

    // MIME-TYPE empty vs set
    'POST /v{ver}/orders/ application/json' => [
        'controller' => 'OrdersJsonController',
        'filters' => ['ver' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY]],
    ],

    // static vs dynamic with mime vs dynamic without mime
    '/v1/avatars/' => [
        // method GET by default if omitted
        'controller' => 'AvatarsController',
        'filters' => [
            'limit' => FILTER_VALIDATE_INT,
            'offset' => FILTER_VALIDATE_INT,
        ],
    ],
    'GET /v{ver}/avatars/ application/json' => [
        'controller' => 'AvatarsGetJsonController',
        'filters' => ['ver' => FILTER_VALIDATE_INT],
    ],
    'GET /v{ver}/avatars' => [
        'controller' => 'AvatarsGetController',
        'filters' => ['ver' => FILTER_VALIDATE_INT],
    ],
    'PUT /v{ver}/avatars' => [
        'controller' => 'AvatarsPutController',
        'filters' => ['ver' => FILTER_VALIDATE_INT],
    ],

    // callback to split parameter on array and only keep integer values
    'GET /products/{id_list}' => [
        'controller' => 'ProductsListController',
        'filters' => [
            'id_list' => [
                'filter' => FILTER_CALLBACK,
                'options' => function ($v) {
                    return array_values(array_map('intval', array_filter(explode(',', $v), 'is_numeric'))) ?: false;
                },
            ],
        ],
    ]
];
