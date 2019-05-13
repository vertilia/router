<?php

namespace Vertilia\Router;

use PHPUnit\Framework\TestCase;
use Vertilia\Request\HttpRequest;

/**
 * @coversDefaultClass \Vertilia\Router\HttpRouter
 */
class HttpRouterTest extends TestCase
{
    /**
     * @dataProvider httpRouterProvider
     * @covers ::__construct
     * @covers ::parseRoute
     * @covers ::getResponseController
     * @param array $server
     * @param array $get
     * @param array $post
     * @param array $cookie
     * @param string $php_input
     * @param string $controller
     * @param string $default_controller
     * @param array $args
     */
    public function testHttpRouter1($server, $get, $post, $cookie, $php_input, $controller, $default_controller, $args)
    {
        // set request filters
        $filters = [];
        if (isset($args)) {
            foreach ($args as $k => $v) {
                $filters[$k] = \is_int($v) ? \FILTER_VALIDATE_INT : \FILTER_DEFAULT;
            }
        }

        // new request
        $request = new HttpRequest($server, $get, $post, $cookie, $php_input, $filters);

        // router table within constructor
        $router = new HttpRouter($request, [__DIR__ . '/http-routes.php']);
        $this->assertInstanceOf(RouterInterface::class, $router);
        $this->assertTrue(\is_bool($router->parseRoute()));

        // check controller
        $this->assertEquals($controller, $router->getResponseController($default_controller));

        // check arguments
        if (isset($args)) {
            foreach ($args as $k => $v) {
                $this->assertTrue(isset($request[$k]));
                $this->assertEquals($v, $request[$k]);
                unset($request[$k]);
            }
        }
    }

    /**
     * @dataProvider httpRouterProvider
     * @covers ::__construct
     * @covers ::addRoutes
     * @covers ::parseRoute
     * @covers ::getResponseController
     * @param array $server
     * @param array $get
     * @param array $post
     * @param array $cookie
     * @param string $php_input
     * @param string $controller
     * @param string $default_controller
     * @param array $args
     */
    public function testHttpRouter2($server, $get, $post, $cookie, $php_input, $controller, $default_controller, $args)
    {
        // set request filters
        $filters = [];
        if (isset($args)) {
            foreach ($args as $k => $v) {
                $filters[$k] = \is_int($v) ? \FILTER_VALIDATE_INT : \FILTER_DEFAULT;
            }
        }

        // set routes in constructor
        $request = new HttpRequest($server, $get, $post, $cookie, $php_input, $filters);

        // set routes outside the constructor
        $router = new HttpRouter($request);
        $this->assertTrue(\is_bool($router->addRoutes(include __DIR__.'/http-routes.php')->parseRoute()));

        // check controller
        $this->assertEquals($controller, $router->getResponseController($default_controller));

        // check arguments
        if (isset($args)) {
            foreach ($args as $k => $v) {
                $this->assertTrue(isset($request[$k]));
                $this->assertEquals($v, $request[$k]);
            }
        }
    }

    /** data provider */
    public function httpRouterProvider()
    {
        // method, path,
        //  content_type, request, php_input | controller, default_controller, args

        // [server],
        //  get, post, cookie, php_input | controller, default_controller, args
        return [
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'IndexController', null, null],

            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v1///users//./q', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'UsersController', null, null],

            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v1///products//./', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'ProductsController', null, null],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/v2///products/123//./../456', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'ProductsController', null, ['ver'=>'v2', 'id'=>456]],
            [['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/v2///products/123//./../456', 'HTTP_CONTENT_TYPE' => null],
                null, ['a' => 'b', 'c' => 'd'], null, null, 'ProductsController', null, ['ver'=>'v2', 'id'=>456, 'a'=>'b', 'c'=>'d']],
            [['REQUEST_METHOD' => 'PUT', 'REQUEST_URI' => '/v2///products/123//./../456', 'HTTP_CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
                null, null, null, 'e=f&g=h', 'ProductsController', null, ['ver'=>'v2', 'id'=>456, 'e'=>'f', 'g'=>'h']],
            [['REQUEST_METHOD' => 'PATCH', 'REQUEST_URI' => '/v2///products/123//./../456', 'HTTP_CONTENT_TYPE' => 'application/json'],
                null, null, null, '{"e":"f","g":"h"}', 'ProductsController', null, ['ver'=>'v2', 'id'=>456, 'e'=>'f', 'g'=>'h']],
            [['REQUEST_METHOD' => 'DELETE', 'REQUEST_URI' => '/v2///products/123//./../456', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'ProductsController', null, ['ver'=>'v2', 'id'=>456]],

            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '**UNDEFINED_ROUTE**', 'HTTP_CONTENT_TYPE' => null],
                null, null, null, null, 'UndefinedController', 'UndefinedController', null],
        ];
    }
}
