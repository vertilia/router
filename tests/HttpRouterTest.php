<?php

namespace Vertilia\Router;

use PHPUnit\Framework\TestCase;
use Vertilia\Request\HttpRequest;

/**
 * @coversDefaultClass HttpRouter
 */
class HttpRouterTest extends TestCase
{
    /**
     * @dataProvider providerHttpRouter
     * @covers HttpRouter::__construct
     * @covers HttpRouter::getControllerFromRequest
     */
    public function testHttpRouter(
        HttpRequest $request,
        array $expected_leaf_node,
        ?array $expected_parameters = null,
        ?string $default_controller = null
    ) {
        // load and parse routes
        $router = (new HttpRouter($request, [__DIR__ . '/http-routes.php']));

        // get leaf data from the router, extract "filters" if present
        $leaf_data = $router->getControllerFromRequest($default_controller);
        unset($leaf_data['filters']);

        // verify leaf data
        $this->assertSame($expected_leaf_node, $leaf_data);

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
    public static function providerHttpRouter(): array
    {
        return include __DIR__ . '/provider.php';
    }
}
