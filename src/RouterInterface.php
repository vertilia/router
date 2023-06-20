<?php

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
     * Add a new list of routes to existing ones
     *
     * @param array $routes
     * @return RouterInterface
     */
    public function parseRoutes(array $routes): RouterInterface;

    /**
     * Return current routing table to reuse for fast loading
     *
     * @return array
     */
    public function getParsedRoutes(): array;

    /**
     * Load routing table without parsing
     *
     * @param array $parsed_routes
     * @return self
     */
    public function setParsedRoutes(array $parsed_routes): self;

    /**
     * Returns controller matching current request for the routing table
     *
     * @param string $route format: METHOD PATH [CONTENT-TYPE], ex: "POST /api/login application/json" or "GET /"
     * @param ?string $default_controller name of the default controller
     * @return ?array found leaf structure. if leaf structure is a scalar, it will be returned as an array with element
     *  name "controller". if parameters were detected within the route, "parameters" element will be added to the
     *  resulting array with associative array of detected parameters.
     *  will set "controller" element to $default_controller if route not found.
     */
    public function getController(string $route, ?string $default_controller = null): ?array;
}
