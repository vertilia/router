<?php
declare(strict_types=1);

namespace Vertilia\Router;

use Vertilia\Filesystem\Filesystem;
use Vertilia\Parser\ParserInterface;
use Vertilia\Request\HttpRequestInterface;

class HttpRouter implements RouterInterface
{
    /** @var HttpRequestInterface */
    protected HttpRequestInterface $request;
    /** @var ParserInterface */
    protected ParserInterface $parser;
    /** @var array */
    protected array $routes = [];

    /**
     * When array of files is provided, latter entries overwrite existing ones
     *
     * @param HttpRequestInterface $request
     * @param ParserInterface|null $parser
     * @param array|null $routes_path path to configuration file (or a list of
     *  configuration files to load)
     */
    public function __construct(HttpRequestInterface $request, ParserInterface $parser = null, array $routes_path = null)
    {
        $this->request = $request;
        isset($parser) and $this->parser = $parser;

        // set routes
        if (isset($routes_path)) {
            foreach ($routes_path as $file) {
                $routes = include $file;
                if (!is_array($routes)) {
                    throw new MalformedRoutingTableException(sprintf(
                        'Routing table in %s must return an array',
                        $file
                    ));
                }

                $this->parseRoutes($routes);
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
     * Regex version is used when {var-name} parameters are provided in path
     *
     * If controller name is not provided, it is considered to be the path with
     * forward slashes translated to back slashes to separate namespaces
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
    public function parseRoutes(array $routes): RouterInterface
    {
        $struct = [];
        foreach ($routes as $k => $v) {
            $method = $path = $mime = null;
            $path_mode = null;
            if (is_string($v)) {
                if (is_string($k)) {
                    $route = $k;
                    $controller = $v;
                } else {
                    $route = $v;
                    $controller = null;
                }
                $filters = null;
            } elseif (is_array($v)) {
                $route = is_string($k)
                    ? $k
                    : ($v['route'] ?? null);
                if (isset($v['path-static'])) {
                    $path = $v['path-static'];
                    $path_mode = 'path-static';
                } elseif (isset($v['path-regex'])) {
                    $path = $v['path-regex'];
                    $path_mode = 'path-regex';
                }
                $method = $v['method'] ?? null;
                $mime = $v['mime-type'] ?? null;
                $controller = $v['controller'] ?? null;
                $filters = $v['filters'] ?? null;
            } else {
                $route = null;
                $controller = null;
                $filters = null;
            }

            if (isset($route)) {
                $parts = preg_split('/\s+/', $route, 3);
                switch (count($parts)) {
                    case 1:
                        $path = $method;
                        $method = 'GET';
                        $mime = null;
                        break;
                    case 2:
                        list($method, $path) = $parts;
                        $mime = null;
                        break;
                    default:
                        list($method, $path, $mime) = $parts;
                }
            } else {
                if (empty($method)) {
                    $method = 'GET';
                }
            }

            $method_type = rtrim("$method $mime");
            $path_normalized = Filesystem::normalizePath($path);
            $ctr = $controller
                ?? ($path_normalized === ''
                    ? 'index'
                    : preg_replace(['#[^\w/]+#', '#/#'], ['_', '\\'], $path_normalized)
                );

            switch ($path_mode) {
                case 'path-static':
                    $struct[$method_type]['static']["/$path_normalized"] = $ctr;
                    break;

                case 'path-regex':
                    $struct[$method_type]['regex'][$path] = isset($filters)
                        ? [$controller, $filters]
                        : $controller;
                    break;

                default:
                    if (strpos($path_normalized, '{') === false) {
                        $struct[$method_type]['static']["/$path_normalized"] = $ctr;
                    } elseif (isset($this->parser)) {
                        $pattern = $this->parser->getRegex("/$path_normalized");
                        $struct[$method_type]['regex'][$pattern] = isset($filters)
                            ? [$ctr, $filters]
                            : $ctr;
                    }
            }
        }

        $this->routes = array_replace_recursive($this->routes, $struct);

        return $this;
    }

    /** @inheritDoc */
    public function getParsedRoutes(): array
    {
        return $this->routes;
    }

    /** @inheritDoc */
    public function setParsedRoutes(array $parsed_routes): self
    {
        $this->routes = $parsed_routes;
        return $this;
    }

    /**
     * Returns controller matching current request for the routing table
     *
     * If filters were provided for the identified route they will be added to
     * request before registering path parameters
     *
     * If filters were not provided, path parameters will be added to request
     * only if request already contains corresponding filters
     *
     * If route was not identified, default controller is returned
     *
     * @param string|null $default_controller
     * @return string
     */
    public function getController(string $default_controller = null): ?string
    {
        $method = $this->request->getMethod();
        $type = $this->request->getHeaders()['content-type'] ?? null;
        if ($type) {
            $method_type = "$method $type";
            $sources = [$method_type, $method];
        } else {
            $method_type = $method;
            $sources = [$method];
        }

        if (empty($this->routes) or ! is_array($this->routes)) {
            return $default_controller;
        } elseif (empty($this->routes[$method_type]) and empty($this->routes[$method])) {
            return $default_controller;
        }

        $path_normalized = '/' . Filesystem::normalizePath($this->request->getPath());

        // check static path in "METHOD TYPE"
        if (isset($this->routes[$method_type]['static'][$path_normalized])) {
            return $this->routes[$method_type]['static'][$path_normalized];
        // check static path in "METHOD"
        } elseif (isset($this->routes[$method]['static'][$path_normalized])) {
            return $this->routes[$method]['static'][$path_normalized];
        }

        // check regex paths in "METHOD TYPE" and "METHOD"
        foreach ($sources as $m_t) {
            if (isset($this->routes[$m_t]['regex'])) {
                foreach ($this->routes[$m_t]['regex'] as $regex => $ctr_flt) {
                    $m = null;
                    if (preg_match($regex, $path_normalized, $m)) {
                        if (is_array($ctr_flt)) {
                            list ($controller, $filters) = $ctr_flt;
                            if ($filters) {
                                $this->request->addFilters($filters);
                            }
                        } else {
                            $controller = $ctr_flt;
                        }

                        foreach ($m as $k => $v) {
                            if (is_string($k)) {
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
