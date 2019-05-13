<?php

return [
    'GET /' => 'IndexController',

    'GET /{ver}/products' => 'ProductsController',
    'GET /{ver}/products/{id}' => 'ProductsController',
    'POST /{ver}/products/{id}' => 'ProductsController',
    'PUT /{ver}/products/{id}' => 'ProductsController',
    'PATCH /{ver}/products/{id}' => 'ProductsController',
    'DELETE /{ver}/products/{id}' => 'ProductsController',

    'GET /{ver}/users/{id}' => 'UsersController',
];
