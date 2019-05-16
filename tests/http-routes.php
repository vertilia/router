<?php

return [
    // path in keys => controller in values
//    'GET /' => 'IndexController',

    'GET /{ver}/products' => 'ProductsController',
    'GET /{ver}/products/{id}' => 'ProductsController',
    'POST /{ver}/products/{id}' => 'ProductsController',
    'PUT /{ver}/products/{id}' => 'ProductsController',
    'PATCH /{ver}/products/{id}' => 'ProductsController',
    'DELETE /{ver}/products/{id}' => 'ProductsController',

    'GET /{ver}/users/{id}' => 'UsersController',

    // path-only values, controller from path
    'GET /',
    'GET /api/contracts',
    'GET /--api--/--contracts--/--',
    'DELETE /api/contracts/{id}',
    'GET /api/users--up/{id}/some.controller',
    'GET /{o}.{n}.{c}.{e}.../--users-{id}-down--/--{ver}.controller--',
];
