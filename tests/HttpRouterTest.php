<?php

namespace Vertilia\Router;

use PHPUnit\Framework\TestCase;
use Vertilia\Parser\OpenApiParser;
use Vertilia\Request\HttpRequest;

/**
 * @coversDefaultClass \Vertilia\Router\HttpRouter
 */
class HttpRouterTest extends TestCase
{
    /**
     * Test different instantiation methods.
     *
     * @covers ::__construct
     * @covers ::parseRoutes
     */
    public function testHttpRouterConstructor()
    {
        // new request
        $request = new HttpRequest([]);

        // new OpenApi parser
        $parser = new OpenApiParser();

        // router table within constructor as array
        $router1 = new HttpRouter($request, $parser, [__DIR__.'/http-routes.php']);
        $this->assertInstanceOf(RouterInterface::class, $router1);

        // router table from setter
        $router2 = new HttpRouter($request, $parser);
        $router2->parseRoutes(include __DIR__.'/http-routes.php');
        $this->assertInstanceOf(RouterInterface::class, $router2);
    }

    /**
     * @dataProvider providerHttpRouter
     * @covers ::__construct
     * @covers ::getParsedRoutes
     * @covers ::setParsedRoutes
     * @covers ::getController
     */
    public function testHttpRouter(
        array $server,
        ?array $get,
        ?array $post,
        ?array $cookie,
        ?string $php_input,
        string $controller,
        ?string $default_controller,
        ?array $args,
        ?array $filters
    ) {
        // new request
        $request = new HttpRequest($server, $get, $post, $cookie, null, $php_input, $filters);

        // new OpenAPI parser
        $routes = (new HttpRouter($request, new OpenApiParser(), [__DIR__ . '/http-routes.php']))->getParsedRoutes();
        $router = (new HttpRouter($request))->setParsedRoutes($routes);

        // check controller
        $this->assertEquals($controller, $router->getController($default_controller));

        // check arguments
        if (isset($args)) {
            foreach ($args as $k => $v) {
                $this->assertTrue(
                    $v === ($request[$k] ?? null),
                    sprintf('{%s: %s} expecting %s', $k, serialize($request[$k]), serialize($v))
                );
            }
        }
    }

    /** data provider */
    public static function providerHttpRouter(): array
    {
        // [server],
        //  get, post, cookie, php_input | controller, default_controller, args, filters
        return [
            'home page' =>
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'Index', null, null, null],

            'users' =>
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v1///users//./q', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'UsersController', null, null, null],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users/5b6d0be2-d47f-11e8-9f9d-ccaf789d94a0'],
                null, null, null, null, 'UsersUuidController', null, ['uuid' => '5b6d0be2-d47f-11e8-9f9d-ccaf789d94a0'], null],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users/unknown'],
                null, null, null, null, 'UsersUuidController', null, ['uuid' => false], null],

            'products' =>
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v1///products//./', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'ProductsController', null, null, null],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v2///products/123//./../456', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'ProductsController', null, ['ver'=>'v2', 'id'=>'456'], ['ver' => FILTER_DEFAULT, 'id' => FILTER_VALIDATE_INT]],
            [['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/v2///products/123//./../456', 'HTTP_CONTENT_TYPE' => null],
                null, ['a' => 'b', 'c' => 'd'], null, null, 'ProductsController', null,
                ['ver'=>'v2', 'id'=>'456', 'a'=>'b', 'c'=>'d'],
                ['ver' => FILTER_DEFAULT, 'id' => FILTER_VALIDATE_INT, 'a' => FILTER_DEFAULT, 'c' => FILTER_DEFAULT]
            ],
            [['REQUEST_METHOD' => 'PUT', 'REQUEST_URI' => '/v2///products/123//./../456', 'HTTP_CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
                null, null, null, 'e=f&g=h', 'ProductsController', null,
                ['ver'=>'v2', 'id'=>'456', 'e'=>'f', 'g'=>'h'],
                ['ver' => FILTER_DEFAULT, 'id' => FILTER_VALIDATE_INT, 'e' => FILTER_DEFAULT, 'g' => FILTER_DEFAULT]
            ],
            [['REQUEST_METHOD' => 'PATCH', 'REQUEST_URI' => '/v2///products/123//./../456', 'HTTP_CONTENT_TYPE' => 'application/json'],
                null, null, null, '{"e":"f","g":"h"}', 'ProductsController', null,
                ['ver'=>'v2', 'id'=>'456', 'e'=>'f', 'g'=>'h'],
                ['ver' => FILTER_DEFAULT, 'id' => FILTER_VALIDATE_INT, 'e' => FILTER_DEFAULT, 'g' => FILTER_DEFAULT]
            ],
            [['REQUEST_METHOD' => 'DELETE', 'REQUEST_URI' => '/v2///products/123//./../456', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'ProductsController', null,
                ['ver'=>'v2', 'id'=>'456'],
                ['ver' => FILTER_DEFAULT, 'id' => FILTER_VALIDATE_INT]
            ],

            'undefined route' =>
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '**UNDEFINED_ROUTE**', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'UndefinedController', 'UndefinedController', null, null],

            'contracts and users' =>
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/contracts', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'Api\\Contracts', null, null, null],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/--api--/--contracts--/--', 'HTTP_CONTENT_TYPE' => 'text/html'],
                null, null, null, null, 'Api\\Contracts', null, null, null],
            [['REQUEST_METHOD' => 'DELETE', 'REQUEST_URI' => '/api/contracts/123', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'Api\\Contracts\\Id', null, ['id' => '123'], ['id' => FILTER_VALIDATE_INT]],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/users--up/123/some.controller', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'Api\\UsersUp\\Id\\SomeController', null, ['id' => '123'], ['id' => FILTER_VALIDATE_INT]],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/o.n.c.e.../--users-123-down--/--234.controller--', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'ONCE\\UsersIdDown\\VerController', null,
                ['ver' => '234', 'id' => '123'],
                ['ver' => FILTER_VALIDATE_INT, 'id' => FILTER_VALIDATE_INT]
            ],

            'orders' =>
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v1/orders/15', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'OrdersController', null, ['ver' => '1', 'id' => 15], null],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v1/orders/-1', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'OrdersController', null, ['ver' => '1', 'id' => false], null],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v1/orders/---', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'OrdersController', null, ['ver' => '1', 'id' => false], null],
            [['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/v1/orders', 'HTTP_CONTENT_TYPE' => 'unknown'],
                null, null, null, null, 'OrdersController', null, ['ver' => [1]], null],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/va%20b/orders/1'],
                null, null, null, null, 'OrdersController', null, ['ver' => 'a b', 'id' => 1], null],
            [['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/v1/orders', 'HTTP_CONTENT_TYPE' => 'application/json'],
                null, null, null, null, 'OrdersJsonController', null, ['ver' => [1]], null],

            'avatars' =>
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v1/avatars'],
                null, null, null, null, 'AvatarsController', null, null, null],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v2/avatars', 'HTTP_CONTENT_TYPE' => 'application/json'],
                null, null, null, null, 'AvatarsGetJsonController', null, ['ver' => 2], null],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v3/avatars'],
                null, null, null, null, 'AvatarsGetController', null, ['ver' => 3], null],
            [['REQUEST_METHOD' => 'PUT', 'REQUEST_URI' => '/v4/avatars'],
                null, null, null, null, 'AvatarsPutController', null, ['ver' => 4], null],

            'callback' =>
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/products/123,456,xxx,789'],
                null, null, null, null, 'ProductsListController', null, ['id_list' => [123, 456, 789]], null],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/products/xxx,123'],
                null, null, null, null, 'ProductsListController', null, ['id_list' => [123]], null],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/products/xxx'],
                null, null, null, null, 'ProductsListController', null, ['id_list' => false], null],
        ];
    }
}
