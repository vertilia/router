<?php
declare(strict_types=1);

namespace Vertilia\Router;

/**
 * Children must identify a name for the response controller
 * via getResponseController() method.
 */
interface RouterInterface
{
    /**
     * Receives list of routes to use in parseRoute()
     *
     * @param array $routes
     * @return RouterInterface
     */
    public function addRoutes(array $routes): RouterInterface;

    /**
    * Tries to match current request to specific controller from the routing
    * table stored by addRoutes().
    *
    * @return bool whether controller was found
    */
    public function parseRoute(): bool;

    /**
     * @param string|null $default_controller
     * @return string controller name found with parseRoute() or
     *  $default_controller value
     */
    public function getResponseController(string $default_controller = null): ?string;
}
