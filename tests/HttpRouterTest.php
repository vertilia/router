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
     * @dataProvider httpRouterProvider
     * @covers ::__construct
     * @covers ::getParsedRoutes
     * @covers ::setParsedRoutes
     * @covers ::getController
     * @param array $server
     * @param array $get
     * @param array $post
     * @param array $cookie
     * @param string $php_input
     * @param string $controller
     * @param string $default_controller
     * @param array $args
     * @param array $filters
     */
    public function testHttpRouter($server, $get, $post, $cookie, $php_input, $controller, $default_controller, $args, $filters)
    {
        // new request
        $request = new HttpRequest($server?:[], $get, $post, $cookie, null, $php_input, $filters);

        // new OpenAPI parser
        $parser = new OpenApiParser();

        $routes = (new HttpRouter($request, $parser, [__DIR__ . '/http-routes.php']))->getParsedRoutes();
        $router = (new HttpRouter($request, $parser))->setParsedRoutes($routes);

        // check controller
        $this->assertEquals($controller, $router->getController($default_controller));

        // check arguments
        if (isset($args)) {
            foreach ($args as $k => $v) {
                $this->assertTrue($v === ($request[$k] ?? null));
            }
        }
    }

    /** data provider */
    public function httpRouterProvider()
    {
        // [server],
        //  get, post, cookie, php_input | controller, default_controller, args, filters
        return [
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'index', null, null, null],

            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v1///users//./q', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'UsersController', null, null, null],

            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v1///products//./', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'ProductsController', null, null, null],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v2///products/123//./../456', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'ProductsController', null, ['ver'=>'v2', 'id'=>456], ['ver' => FILTER_DEFAULT, 'id' => FILTER_VALIDATE_INT]],
            [['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/v2///products/123//./../456', 'HTTP_CONTENT_TYPE' => null],
                null, ['a' => 'b', 'c' => 'd'], null, null, 'ProductsController', null,
                ['ver'=>'v2', 'id'=>456, 'a'=>'b', 'c'=>'d'],
                ['ver' => FILTER_DEFAULT, 'id' => FILTER_VALIDATE_INT, 'a' => FILTER_DEFAULT, 'c' => FILTER_DEFAULT]
            ],
            [['REQUEST_METHOD' => 'PUT', 'REQUEST_URI' => '/v2///products/123//./../456', 'HTTP_CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
                null, null, null, 'e=f&g=h', 'ProductsController', null,
                ['ver'=>'v2', 'id'=>456, 'e'=>'f', 'g'=>'h'],
                ['ver' => FILTER_DEFAULT, 'id' => FILTER_VALIDATE_INT, 'e' => FILTER_DEFAULT, 'g' => FILTER_DEFAULT]
            ],
            [['REQUEST_METHOD' => 'PATCH', 'REQUEST_URI' => '/v2///products/123//./../456', 'HTTP_CONTENT_TYPE' => 'application/json'],
                null, null, null, '{"e":"f","g":"h"}', 'ProductsController', null,
                ['ver'=>'v2', 'id'=>456, 'e'=>'f', 'g'=>'h'],
                ['ver' => FILTER_DEFAULT, 'id' => FILTER_VALIDATE_INT, 'e' => FILTER_DEFAULT, 'g' => FILTER_DEFAULT]
            ],
            [['REQUEST_METHOD' => 'DELETE', 'REQUEST_URI' => '/v2///products/123//./../456', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'ProductsController', null,
                ['ver'=>'v2', 'id'=>456],
                ['ver' => FILTER_DEFAULT, 'id' => FILTER_VALIDATE_INT]
            ],

            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '**UNDEFINED_ROUTE**', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'UndefinedController', 'UndefinedController', null, null],

            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'index', null, null, null],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/contracts', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'api\contracts', null, null, null],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/--api--/--contracts--/--', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, '_api_\_contracts_\_', null, null, null],
            [['REQUEST_METHOD' => 'DELETE', 'REQUEST_URI' => '/api/contracts/123', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'api\contracts\_id_', null, ['id' => 123], ['id' => FILTER_VALIDATE_INT]],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/users--up/123/some.controller', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'api\users_up\_id_\some_controller', null, ['id' => 123], ['id' => FILTER_VALIDATE_INT]],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/o.n.c.e.../--users-123-down--/--234.controller--', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, '_o_n_c_e_\_users_id_down_\_ver_controller_', null,
                ['ver' => 234, 'id' => 123],
                ['ver' => FILTER_VALIDATE_INT, 'id' => FILTER_VALIDATE_INT]
            ],

            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v1/orders/15', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'OrdersController', null, ['ver' => null, 'id' => 15], null],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v1/orders/-1', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'OrdersController', null, ['ver' => null, 'id' => false], null],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v1/orders/---', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'OrdersController', null, ['ver' => null, 'id' => false], null],

            [['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/v1/orders', 'HTTP_CONTENT_TYPE' => 'unknown'],
                null, null, null, null, 'OrdersController', null, ['ver' => [1]], null],

            [['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/v1/orders', 'HTTP_CONTENT_TYPE' => 'application/json'],
                null, null, null, null, 'OrdersJsonController', null, ['ver' => [1]], null],

            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v1/avatars'],
                null, null, null, null, 'AvatarsController', null, null, null],
            [['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/v2/avatars', 'HTTP_CONTENT_TYPE' => 'application/json'],
                null, null, null, null, 'AvatarsPostJsonController', null, ['ver' => 2], null],
            [['REQUEST_METHOD' => 'PUT', 'REQUEST_URI' => '/v3/avatars'],
                null, null, null, null, 'AvatarsPutController', null, ['ver' => 3], null],
        ];
    }
}
