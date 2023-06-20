<?php

use Vertilia\Request\HttpRequest;

// HttpRequest, leaf_node, parameters, default_controller,

return [
    'home page' =>
    [new HttpRequest(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTP_CONTENT_TYPE' => null]),
        ['controller' => 'Index']],

    'users' =>
    [new HttpRequest(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v1///users//./q', 'HTTP_CONTENT_TYPE' => null]),
        ['controller' => 'UsersController', 'parameters' => ['ver' => 'v1', 'id' => 'q']]],
    [new HttpRequest(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users/5b6d0be2-d47f-11e8-9f9d-ccaf789d94a0']),
        ['controller' => 'UsersUuidController', 'parameters' => ['uuid' => '5b6d0be2-d47f-11e8-9f9d-ccaf789d94a0']],
        ['uuid' => '5b6d0be2-d47f-11e8-9f9d-ccaf789d94a0']],
    [new HttpRequest(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users/unknown']),
        ['controller' => 'UsersUuidController', 'parameters' => ['uuid' => 'unknown']],
        ['uuid' => false]],

    'products' =>
    [new HttpRequest(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v1///products//./', 'HTTP_CONTENT_TYPE' => null]),
        ['controller' => 'ProductsController', 'parameters' => ['ver' => 'v1']]],
    [new HttpRequest(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v2///products/123//./../456', 'HTTP_CONTENT_TYPE' => null], [], [], [], [], '', ['ver' => FILTER_DEFAULT, 'id' => FILTER_VALIDATE_INT]),
        ['controller' => 'ProductsController', 'parameters' => ['ver' => 'v2', 'id' => '456']],
        ['ver'=>'v2', 'id'=>456]],
    [new HttpRequest(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/v2///products/123//./../456', 'HTTP_CONTENT_TYPE' => null], [], ['a' => 'b', 'c' => 'd'], [], [], '', ['ver' => FILTER_DEFAULT, 'id' => FILTER_VALIDATE_INT, 'a' => FILTER_DEFAULT, 'c' => FILTER_DEFAULT]),
        ['controller' => 'ProductsController', 'parameters' => ['ver' => 'v2', 'id' => '456']],
        ['ver'=>'v2', 'id'=>456, 'a'=>'b', 'c'=>'d'],
    ],
    [new HttpRequest(['REQUEST_METHOD' => 'PUT', 'REQUEST_URI' => '/v2///products/123//./../456', 'HTTP_CONTENT_TYPE' => 'application/x-www-form-urlencoded'], [], [], [], [], 'e=f&g=h', ['ver' => FILTER_DEFAULT, 'id' => FILTER_VALIDATE_INT, 'e' => FILTER_DEFAULT, 'g' => FILTER_DEFAULT]),
        ['controller' => 'ProductsController', 'parameters' => ['ver' => 'v2', 'id' => '456']],
        ['ver'=>'v2', 'id'=>456, 'e'=>'f', 'g'=>'h'],
    ],
    [new HttpRequest(['REQUEST_METHOD' => 'PATCH', 'REQUEST_URI' => '/v2///products/123//./../456', 'HTTP_CONTENT_TYPE' => 'application/json'], [], [], [], [], '{"e":"f","g":"h"}', ['ver' => FILTER_DEFAULT, 'id' => FILTER_VALIDATE_INT, 'e' => FILTER_DEFAULT, 'g' => FILTER_DEFAULT]),
        ['controller' => 'ProductsController', 'parameters' => ['ver' => 'v2', 'id' => '456']],
        ['ver'=>'v2', 'id'=>456, 'e'=>'f', 'g'=>'h'],
    ],
    [new HttpRequest(['REQUEST_METHOD' => 'DELETE', 'REQUEST_URI' => '/v2///products/123//./../456', 'HTTP_CONTENT_TYPE' => null], [], [], [], [], '', ['ver' => FILTER_DEFAULT, 'id' => FILTER_VALIDATE_INT]),
        ['controller' => 'ProductsController', 'parameters' => ['ver' => 'v2', 'id' => '456']],
        ['ver'=>'v2', 'id'=>456]],

    'undefined route' =>
    [new HttpRequest(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '**UNDEFINED_ROUTE**', 'HTTP_CONTENT_TYPE' => null]),
        ['controller' => 'UndefinedController'],
        [], 'UndefinedController'],

    'contracts and users' =>
    [new HttpRequest(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/contracts', 'HTTP_CONTENT_TYPE' => null]),
        ['controller' => 'Api\\Contracts']],
    [new HttpRequest(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/--api--/--contracts--/--', 'HTTP_CONTENT_TYPE' => 'text/html']),
        ['controller' => 'Api\\Contracts']],
    [new HttpRequest(['REQUEST_METHOD' => 'DELETE', 'REQUEST_URI' => '/api/contracts/123', 'HTTP_CONTENT_TYPE' => null], [], [], [], [], '', ['id' => FILTER_VALIDATE_INT]),
        ['controller' => 'Api\\Contracts\\Id', 'parameters' => ['id' => '123']],
        ['id' => 123]],
    [new HttpRequest(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/users--up/123/some.controller', 'HTTP_CONTENT_TYPE' => null], [], [], [], [], '', ['id' => FILTER_VALIDATE_INT]),
        ['controller' => 'Api\\UsersUp\\Id\\SomeController', 'parameters' => ['id' => '123']],
        ['id' => 123]],
    [new HttpRequest(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/o.n.c.e.../--users-123-down--/--234.controller--', 'HTTP_CONTENT_TYPE' => null], [], [], [], [], '', ['ver' => FILTER_VALIDATE_INT, 'id' => FILTER_VALIDATE_INT]),
        ['controller' => 'ONCE\\UsersIdDown\\VerController', 'parameters' => ['o' => 'o', 'n' => 'n', 'c' => 'c', 'e' => 'e', 'id' => '123', 'ver' => '234']],
        ['ver' => 234, 'id' => 123]],

    'orders' =>
    [new HttpRequest(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v1/orders/15', 'HTTP_CONTENT_TYPE' => null]),
        ['controller' => 'OrdersController', 'parameters' => ['ver' => '1', 'id' => '15']],
        ['id' => 15]],
    [new HttpRequest(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v1/orders/-1', 'HTTP_CONTENT_TYPE' => null]),
        ['controller' => 'OrdersController', 'parameters' => ['ver' => '1', 'id' => '-1']],
        ['id' => false]],
    [new HttpRequest(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v1/orders/---', 'HTTP_CONTENT_TYPE' => null]),
        ['controller' => 'OrdersController', 'parameters' => ['ver' => '1', 'id' => '---']],
        ['id' => false]],
    [new HttpRequest(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/v1/orders', 'HTTP_CONTENT_TYPE' => 'unknown']),
        ['controller' => 'OrdersController', 'parameters' => ['ver' => '1']],
        ['ver' => [1]]],
    [new HttpRequest(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/va%20b/orders/1']),
        ['controller' => 'OrdersController', 'parameters' => ['ver' => 'a b', 'id' => '1']],
        ['id' => 1]],
    [new HttpRequest(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/v1/orders', 'HTTP_CONTENT_TYPE' => 'application/json']),
        ['controller' => 'OrdersJsonController', 'parameters' => ['ver' => '1']],
        ['ver' => [1]]],

    'avatars static' =>
    [new HttpRequest(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v1/avatars?limit=10&offset=50'], ['limit'=>10, 'offset' => 50]),
        ['controller' => 'AvatarsController'],
        ['limit' => 10, 'offset' => 50]],
    [new HttpRequest(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v1/avatars']),
        ['controller' => 'AvatarsController'],
        ['limit' => null, 'offset' => null]],

    'avatars dynamic' =>
    [new HttpRequest(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v2/avatars', 'HTTP_CONTENT_TYPE' => 'application/json']),
        ['controller' => 'AvatarsGetJsonController', 'parameters' => ['ver' => '2']],
        ['ver' => 2]],
    [new HttpRequest(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v3/avatars']),
        ['controller' => 'AvatarsGetController', 'parameters' => ['ver' => '3']],
        ['ver' => 3]],
    [new HttpRequest(['REQUEST_METHOD' => 'PUT', 'REQUEST_URI' => '/v4/avatars']),
        ['controller' => 'AvatarsPutController', 'parameters' => ['ver' => '4']],
        ['ver' => 4]],

    'callback' =>
    [new HttpRequest(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/products/123,456,xxx,789']),
        ['controller' => 'ProductsListController', 'parameters' => ['id_list' => '123,456,xxx,789']],
        ['id_list' => [123, 456, 789]]],
    [new HttpRequest(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/products/xxx,123']),
        ['controller' => 'ProductsListController', 'parameters' => ['id_list' => 'xxx,123']],
        ['id_list' => [123]]],
    [new HttpRequest(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/products/xxx']),
        ['controller' => 'ProductsListController', 'parameters' => ['id_list' => 'xxx']],
        ['id_list' => false]],
];
