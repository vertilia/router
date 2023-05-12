<?php
declare(strict_types=1);

namespace Vertilia\Router;

/**
 * Children set routing tables via parseRoutes() calls and identify the
 * response controller via getController() method.
 * For faster loading a parsed routing table may be exported with
 * getParsedRoutes(), stored in a file and then imported on each request with
 * setParsedRoutes().
 */
interface RouterInterface
{
    /**
     * Adds a new list of routes to existing ones
     *
     * @param array $routes
     * @return RouterInterface
     */
    public function parseRoutes(array $routes): RouterInterface;

    /**
     * Returns current routing table to reuse for fast loading
     *
     * @return array
     */
    public function getParsedRoutes(): array;

    /**
     * Loads routing table without parsing
     *
     * @param array $parsed_routes
     * @return self
     */
    public function setParsedRoutes(array $parsed_routes): self;

    /**
     * Returns controller matching current request for the routing table
     *
     * @param ?string $default_controller
     * @return ?string controller name from routing table or default controller
     */
    public function getController(?string $default_controller = null): ?string;
}
