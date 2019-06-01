<?php
declare(strict_types=1);

namespace Vertilia\Router;

/**
 * Children set routing tables via addRoutes() calls and identify the response
 * controller via getController() method
 */
interface RouterInterface
{
    /**
     * Adds a new list of routes to existing ones
     *
     * @param array $routes
     * @return RouterInterface
     */
    public function addRoutes(array $routes): RouterInterface;

    /**
     * Returns controller matching current request for the routing table
     *
     * @param string|null $default_controller
     * @return mixed controller name or any structure resolved from the path
     */
    public function getController(string $default_controller = null);
}
