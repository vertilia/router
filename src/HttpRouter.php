<?php
declare(strict_types=1);

namespace Vertilia\Router;

use Vertilia\Parser\ParserInterface;
use Vertilia\Request\HttpRequestInterface;
use Vertilia\Router\MalformedRoutingTableException;

class HttpRouter implements RouterInterface
{
    /** @var HttpRequestInterface */
    protected $request;
    /** @var ParserInterface */
    protected $parser;
    /** @var array */
    protected $routes = [];

    /**
     * When array of files is provided, latter entries overwrite existing ones.
     *
     * @param HttpRequestInterface $request
     * @param array|string $routes_path path to configuration file (or a list of
     *  configuration files to load)
     * @throws MalformedRoutingTableException
     */
    public function __construct(HttpRequestInterface $request, ParserInterface $parser, $routes_path = null)
    {
        $this->request = $request;
        $this->parser = $parser;

        // set routes
        if (isset($routes_path)) {
            foreach ((array) $routes_path as $file) {
                $routes = include $file;
                if (!\is_array($routes)) {
                    throw new MalformedRoutingTableException(\sprintf(
                        'Routing table in %s must return an array',
                        $file
                    ));
                }

                $this->addRoutes($routes);
            }
        }
    }

    /**
     * Receives list of routes in one of recognized formats, then converts and
     * registers the internal version in $routes in the following format:
     * $routes.static: {"METHOD CONTENT-TYPE": {"URI": "CONTROLLER_NAME", ...}, ...} or
     * $routes.regex: {"METHOD CONTENT-TYPE": {"URI_REGEX": "CONTROLLER_NAME", ...}, ...}
     *
     * Recognized formats:
     *  - ["METHOD URI", ...]
     *  - {"METHOD URI": "CONTROLLER", ...}
     *  - {"METHOD URI CONTENT-TYPE": {controller: "CONTROLLER", filters: {FILTERS}}, ...}
     *  - [{route: "METHOD URI CONTENT-TYPE", controller: "CONTROLLER", filters: {FILTERS}}, ...]
     *
     * Regex version is used when {var-name} parameters are provided in path.
     *
     * If controller name is not prodided, it is considered to be the path with
     * forward slashes translated to back slashes to separate namespaces.
     *
     * Filters will be registered for each regex-path where they are provided
     *
     * @param array $routes ex: {
     *  "GET /",
     *  "GET /one/way",
     *  "GET /another/way": "Another\\Way",
     *  "GET /third/way/{id}",
     *  "GET /v1/products/{id}": "App\\ProductsController",
     *  "GET /v1/users-{id}/friends application/json": {
     *      "controller": "App\\UsersController",
     *      "filters": {"id": FILTER_VALIDATE_INT}
     *  }
     * }
     * will register the following in $routes.static: {
     *  "GET": {
     *      "/": "index",
     *      "/one/way": "one\\way",
     *      "/another/way": "another\\way"
     *  }
     * }
     * ...and the following in $routes.regex: {
     *  "GET application/json": {
     *      "#^/v1/users-(?P<id>.+)/friends$#": [
     *          "App\\UsersController",
     *          {"id": FILTER_VALIDATE_INT}
     *      ]
     *  },
     *  "GET": {
     *      "#^/third/way/(?P<id>.+)$#": "third\\way\\_id_",
     *      "#^/v1/products/(?P<id>.+)$#": "App\\ProductsController"
     *  }
     * }
     * @return RouterInterface
     */
    public function addRoutes(array $routes): RouterInterface
    {
        $struct = [];
        foreach ($routes as $k => $v) {
            if (\is_string($v)) {
                if (\is_string($k)) {
                    $route = $k;
                    $controller = $v;
                    $filters = null;
                } else {
                    $route = $v;
                    $controller = null;
                    $filters = null;
                }
            } elseif (\is_array($v)) {
                if (\is_string($k)) {
                    $route = $k;
                    $controller = $v['controller'] ?? null;
                    $filters = $v['filters'] ?? null;
                } else {
                    $route = $v['route'] ?? null;
                    $controller = $v['controller'] ?? null;
                    $filters = $v['filters'] ?? null;
                }
            } else {
                $route = null;
                $controller = null;
                $filters = null;
            }

            list($method, $path, $type) = \preg_split('/\s+/', $route, 3);
            if (!isset($path)) {
                $path = $method;
                $method = 'GET';
            }
            $path_normalized = Fs::normalizePath($path);
            $pattern = $this->parser->getRegex("/$path_normalized");
            $vars = $this->parser->getVars();
            $ctr = $controller
                ?? ($path_normalized === ''
                    ? 'index'
                    : \preg_replace(['#[^\w/]+#', '#/#'], ['_', '\\'], $path_normalized)
                );
            $method_type = \rtrim("$method $type");
            if ($vars) {
                $struct[$method_type]['regex'][$pattern] = isset($filters)
                    ? [$ctr, $filters]
                    : $ctr;
            } else {
                $struct[$method_type]['static']["/$path_normalized"] = $ctr;
            }
        }

        $this->routes = \array_replace_recursive($this->routes, $struct);

        return $this;
    }

    /**
     * Returns controller matching current request for the routing table.
     *
     * If filters were provided for the identified route they will be added to
     * request before registering path parameters.
     *
     * If filters were not provided, path parameters will be added to request
     * only if request already contains corresponding filters.
     *
     * If route wasnot identified, default controller is returned.
     *
     * @param string $default_controller
     * @return string
     */
    public function getController(string $default_controller = null)
    {
        $method = $this->request->getMethod();
        $type = $this->request->getHeaders()['content-type'] ?? null;
        $method_type = \rtrim("$method $type");

        if (empty($this->routes) or ! \is_array($this->routes)) {
            return $default_controller;
        } elseif (empty($this->routes[$method_type]) and empty($this->routes[$method])) {
            return $default_controller;
        }

        $path_normalized = '/' . Fs::normalizePath($this->request->getPath());

        // check static path in "METHOD TYPE"
        if (isset($this->routes[$method_type]['static'][$path_normalized])) {
            return $this->routes[$method_type]['static'][$path_normalized];
        // check static path in "METHOD"
        } elseif (isset($this->routes[$method]['static'][$path_normalized])) {
            return $this->routes[$method]['static'][$path_normalized];
        }

        // check regex paths in "METHOD TYPE" and "METHOD"
        foreach ([$method_type, $method] as $m_t) {
            if (isset($this->routes[$m_t]['regex'])) {
                foreach ($this->routes[$m_t]['regex'] as $regex => $ctr_flt) {
                    $m = null;
                    if (\preg_match($regex, $path_normalized, $m)) {
                        if (\is_array($ctr_flt)) {
                            list ($controller, $filters) = $ctr_flt;
                            if ($filters) {
                                $this->request->addFilters($filters);
                            }
                        } else {
                            $controller = $ctr_flt;
                        }

                        foreach ($m as $k => $v) {
                            if (\is_string($k)) {
                                $this->request[$k] = $v;
                            }
                        }

                        return $controller;
                    }
                }
            }
        }

        // return default
        return $default_controller;
    }
}
