<?php
declare(strict_types=1);

namespace Vertilia\Router;

use Vertilia\Request\HttpRequestInterface;
use Vertilia\Router\MalformedRoutingTableException;

class HttpRouter implements RouterInterface
{
    /** @var HttpRequestInterface */
    protected $request;
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
    public function __construct(HttpRequestInterface $request, $routes_path = null)
    {
        $this->request = $request;

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
     * $routes.static: {"METHOD": {"URI": "CONTROLLER_NAME", ...}, ...} or
     * $routes.regex: {"METHOD": {"URI_REGEX": "CONTROLLER_NAME", ...}, ...}
     *
     * Recognized formats:
     *  - ["METHOD URI", ...]
     *  - {"METHOD URI": "CONTROLLER", ...}
     *  - {"METHOD URI": {controller: "CONTROLLER", filters: {FILTERS}}, ...}
     *  - [{route: "METHOD URI", controller: "CONTROLLER", filters: {FILTERS}}, ...]
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
     *  "GET /v1/users-{id}/friends": {
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
     *  "GET": {
     *      "#^/third/way/(?P<id>[^/]+)$#": "third\\way\\_id_",
     *      "#^/v1/products/(?P<id>[^/]+)$#": "App\\ProductsController",
     *      "#^/v2/users-(?P<id>[^/]+)/friends$#": [
     *          "App\\UsersController",
     *          {"id": FILTER_VALIDATE_INT}
     *      ]
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

            list($method, $path) = \preg_split('/\s+/', $route, 2);
            if (!isset($path)) {
                $path = $method;
                $method = 'GET';
            }
            $path_normalized = Fs::normalizePath($path);
            $var = 0;
            $pattern = '#^/'.\preg_replace(
                '/\\\{([[:alpha:]_]\w*)\\\}/',
                '(?P<$1>[^/]+)',
                \preg_quote($path_normalized, '#'),
                -1,
                $var
            ).'$#';
            $ctr = $controller
                ?? ($path_normalized === ''
                    ? 'index'
                    : \preg_replace(['#[^\w/]+#', '#/#'], ['_', '\\'], $path_normalized)
                );
            if ($var) {
                $struct[$method]['regex'][$pattern] = isset($filters)
                    ? [$ctr, $filters]
                    : $ctr;
            } else {
                $struct[$method]['static']["/$path_normalized"] = $ctr;
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

        if (empty($this->routes)
            or ! \is_array($this->routes)
            or empty($this->routes[$method])
        ) {
            return $default_controller;
        }

        $path_normalized = '/' . Fs::normalizePath($this->request->getPath());

        // check static path
        if (isset($this->routes[$method]['static'])
            and isset($this->routes[$method]['static'][$path_normalized])
        ) {
            return $this->routes[$method]['static'][$path_normalized];
        }

        // check regex path
        if (isset($this->routes[$method]['regex'])) {
            foreach ($this->routes[$method]['regex'] as $regex => $ctr_flt) {
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

        // return default
        return $default_controller;
    }
}
