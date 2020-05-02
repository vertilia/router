<?php

return [
    // path-only values, controller from path
    'GET /',
    'GET /api/contracts',
    'GET /--api--/--contracts--/--',
    'DELETE /api/contracts/{id}',
    'GET /api/users--up/{id}/some.controller',
    'GET /{o}.{n}.{c}.{e}.../--users-{id}-down--/--{ver}.controller--',

    // path in keys => controller in values
    'GET /{ver}/products' => 'ProductsController',
    'GET /{ver}/products/{id}' => 'ProductsController',
    'POST /{ver}/products/{id}' => 'ProductsController',
    'PUT /{ver}/products/{id}' => 'ProductsController',
    'PATCH /{ver}/products/{id}' => 'ProductsController',
    'DELETE /{ver}/products/{id}' => 'ProductsController',

    'GET /{ver}/users/{id}' => 'UsersController',

    // path in keys => controller and filters in array
    'GET /v{ver}/orders/{id}' => [
        'controller' => 'OrdersController',
        'filters' => [
            'id' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 1]],
        ],
    ],

    // route, controller and filters in array
    [
        'route' => 'POST /v{ver}/orders/',
        'controller' => 'OrdersController',
        'filters' => ['ver' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY]],
    ],

    // route, controller and filters in array
    [
        'route' => 'POST /v{ver}/orders/ application/json',
        'controller' => 'OrdersJsonController',
        'filters' => ['ver' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY]],
    ],

    // method, path, mime-type, controller and filters in array
    [
        // methot GET by default if omitted
        'path-static' => '/v1/avatars/',
        'controller' => 'AvatarsController',
    ],
    'POST /v{ver}/avatars/' => [
        'mime-type' => 'application/json',
        'controller' => 'AvatarsPostJsonController',
        'filters' => ['ver' => FILTER_VALIDATE_INT],
    ],
    [
        'method' => 'PUT',
        'path-regex' => '#^/v(?P<ver>.+)/avatars$#',
        'controller' => 'AvatarsPutController',
        'filters' => ['ver' => FILTER_VALIDATE_INT],
    ],
];
