<?php

namespace Vertilia\Router;

use PHPUnit\Framework\TestCase;
use Vertilia\Filesystem\Filesystem;
use Vertilia\Parser\OpenApiParser;
use Vertilia\Request\HttpRequest;

/**
 * @coversDefaultClass Router
 */
class RouterTest extends TestCase
{
    /**
     * Test different instantiation methods.
     *
     * @covers Router::__construct
     * @covers Router::parseRoutes
     */
    public function testRouterConstructor()
    {
        // new OpenApi parser
        $parser = new OpenApiParser();

        // router table within constructor as array
        $router1 = new Router($parser, [__DIR__.'/http-routes.php']);
        $this->assertInstanceOf(RouterInterface::class, $router1);

        // router table from setter
        $router2 = new Router($parser);
        $router2->parseRoutes(include __DIR__.'/http-routes.php');
        $this->assertInstanceOf(RouterInterface::class, $router2);
    }

    /**
     * @dataProvider providerRouter
     * @covers Router::__construct
     * @covers Router::getParsedRoutes
     * @covers Router::setParsedRoutes
     * @covers Router::getController
     */
    public function testRouter(
        HttpRequest $request,
        array $expected_leaf_node,
        ?array $expected_parameters = null,
        ?string $default_controller = null
    ) {
        // load routes and parse by OpenAPI parser
        $routes = (new Router(new OpenApiParser(), [__DIR__ . '/http-routes.php']))->getParsedRoutes();
        $router = (new Router())->setParsedRoutes($routes);

        // route from request
        $method = $request->getMethod();
        $path = '/' . Filesystem::normalizePath($request->getPath());
        $mime = $request->getHeaders()['content-type'] ?? null;

        // get leaf data from the router, extract "filters" if present
        $leaf_data = $router->getController(rtrim("$method $path $mime"), $default_controller);
        if (isset($leaf_data['filters'])) {
            $leaf_filters = $leaf_data['filters'];
            unset($leaf_data['filters']);
        } else {
            $leaf_filters = [];
        }

        // verify leaf data
        $this->assertSame($expected_leaf_node, $leaf_data);

        if ($leaf_filters) {
            $request->addFilters($leaf_filters);
        }

        if (isset($leaf_data['parameters'])) {
            foreach ($leaf_data['parameters'] as $param => $value) {
                $request[$param] = $value;
            }
        }

        // validate parameters
        if (isset($expected_parameters)) {
            foreach ($expected_parameters as $k => $v) {
                $this->assertSame(
                    $v,
                    $request[$k],
                    sprintf('expecting: %s actual: %s for $%s', serialize($v), serialize($request[$k]), $k)
                );
            }
        }
    }

    /** data provider */
    public static function providerRouter(): array
    {
        return include __DIR__ . '/provider.php';
    }
}
