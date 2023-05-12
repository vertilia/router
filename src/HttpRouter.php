<?php
declare(strict_types=1);

namespace Vertilia\Router;

use Vertilia\Filesystem\Filesystem;
use Vertilia\Parser\ParserInterface;
use Vertilia\Request\HttpRequestInterface;

class HttpRouter implements RouterInterface
{
    protected HttpRequestInterface $request;
    protected ?ParserInterface $parser;
    protected array $routes = [];

    /**
     * When array of files is provided, latter entries overwrite existing ones
     *
     * @param HttpRequestInterface $request
     * @param ?ParserInterface $parser
     * @param ?array $routes_path path to configuration file (or a list of configuration files to load)
     */
    public function __construct(HttpRequestInterface $request, ?ParserInterface $parser = null, ?array $routes_path = null)
    {
        $this->request = $request;
        $this->parser = $parser;

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
     * @return HttpRequestInterface
     */
    public function getRequest(): HttpRequestInterface
    {
        return $this->request;
    }

    /**
     * @return ?ParserInterface
     */
    public function getParser(): ?ParserInterface
    {
        return $this->parser;
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
     * forward slashes translated to backslashes to separate namespaces
     *
     * Filters will be registered for each regex-path where they are provided
     *
     * @param array $routes ex: {
     *  "GET /": "Index",
     *  "GET /one/way": "OneWay",
     *  "GET /another/way/{id}": "AnotherWay",
     *  "GET /v1/products/{YM}": {
     *      "controller": "App\\ProductsController",
     *      "filters": {"YM": {"filter": FILTER_VALIDATE_REGEXP, "options": {"regexp": "/^\\d{4}-\\d{2}$/"}}}
     *  },
     *  "GET /v1/users-{id}/friends application/json": {
     *      "controller": "App\\UsersController",
     *      "filters": {"id": FILTER_VALIDATE_INT}
     *  }
     * }
     * will register the following in $routes.static: {
     *  "GET": {
     *      "/": {"controller": "Index"},
     *      "/one/way": {"controller": "OneWay"},
     *  }
     * }
     * ...and the following in $routes.dynamic: {
     *  "GET application/json": {
     *      "v1": {
     *          "re/": {
     *              "#^users-(?P<id>.+)$#": {
     *                  "friends": {
     *                      "result/": {
     *                          "controller": "App\\UsersController",
     *                          "filters": {"id": FILTER_VALIDATE_INT}
     *                      }
     *                  }
     *              }
     *          }
     *      }
     *  },
     *  "GET": {
     *      "another": {
     *          "way": {
     *              "re/": {
     *                  "#^(?P<id>.+)$#": {
     *                      "result/": {
     *                          "controller": "App\\UsersController",
     *                          "filters": {"id": FILTER_DEFAULT}
     *                      }
     *                  }
     *              }
     *          }
     *      },
     *      "v1": {
     *          "products": {
     *              "re/": {
     *                  "#^(?P<id>.+)$#": {
     *                      "result/": {
     *                          "controller": "App\\ProductsController",
     *                          "filters": {"YM": {"filter": FILTER_VALIDATE_REGEXP, "options": {"regexp": "/^\\d{4}-\\d{2}$/"}}}
     *                      }
     *                  }
     *              }
     *          }
     *      }
     *  }
     * }
     * @return RouterInterface
     */
    public function parseRoutes(array $routes): RouterInterface
    {
        $tree = [
            'static' => [],
            'dynamic' => [],
        ];

        foreach ($routes as $route => $op) {
            if (is_string($op)) {
                $op = ['controller' => $op];
            }
            list($method, $path, $mime) = explode(' ', "$route  ");
            if ($mime !== '') {
                $method .= " $mime";
            } elseif ($path === '') {
                $path = $method;
                $method = 'GET';
            }

            if (strpos($path, '{') !== false) {
                $path_dirs = explode('/', trim($path, '/'));
                if (!isset($tree['dynamic'][$method])) {
                    $tree['dynamic'][$method] = [];
                }
                $r = &$tree['dynamic'][$method];
                foreach ($path_dirs as $dir) {
                    if (strpos($dir, '{') !== false) {
                        $pattern = $this->parser->getRegex($dir);
                        foreach ($this->parser->getVars() as $v) {
                            if (!isset($op['filters'][$v])) {
                                $op['filters'][$v] = FILTER_DEFAULT;
                            }
                        }
                        if (!isset($r['re/'])) {
                            $r['re/'] = [];
                        }
                        $r = &$r['re/'];
                        $dir = $pattern;
                    }
                    if (!isset($r[$dir])) {
                        $r[$dir] = [];
                    }
                    $r = &$r[$dir];
                }
                $r['result/'] = $op;
            } else {
                $tree['static'][$method]['/' . trim($path, '/')] = $op;
            }
        }

        $this->routes = array_replace_recursive($this->routes, $tree);

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

    protected function checkLevel(array $subtree, array $path_dirs, array $params = [], int $level = 0): ?array
    {
        $dir = array_shift($path_dirs);

        if (isset($subtree[$dir])) {
            if (empty($path_dirs)) {
                if (isset($subtree[$dir]['result/'])) {
                    $subtree[$dir]['result/']['params'] = $params;
                    return $subtree[$dir]['result/'];
                }
            } else {
                $result = $this->checkLevel($subtree[$dir], $path_dirs, $params, ++$level);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        if (isset($subtree['re/'])) {
            foreach ($subtree['re/'] as $re => $next) {
                if (preg_match($re, $dir, $m)) {
                    $tmp_params = [];
                    foreach ($m as $param => $value) {
                        if (is_string($param)) {
                            $tmp_params[$param] = rawurldecode($value);
                        }
                    }
                    if (empty($path_dirs)) {
                        if (isset($next['result/'])) {
                            $next['result/']['params'] = $params + $tmp_params;
                            return $next['result/'];
                        }
                    } else {
                        $result = $this->checkLevel($next, $path_dirs, $params + $tmp_params, ++$level);
                        if ($result !== null) {
                            return $result;
                        }
                    }
                }
            }
        }

        return null;
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
     * @param ?string $default_controller
     * @return ?string
     */
    public function getController(?string $default_controller = null): ?string
    {
        $method = $this->request->getMethod();
        $mime = $this->request->getHeaders()['content-type'] ?? null;
        $sources = $mime ? ["$method $mime", $method] : [$method];

        if (empty($this->routes)) {
            return $default_controller;
        }
        $x = $this->request->getPath();

        $path_normalized = '/' . Filesystem::normalizePath($x);

        // check static path
        if (isset($this->routes['static']["$method $mime"][$path_normalized])) {
            return $this->routes['static']["$method $mime"][$path_normalized]['controller'];
        } elseif (isset($this->routes['static'][$method][$path_normalized])) {
            return $this->routes['static'][$method][$path_normalized]['controller'];
        }

        // check dynamic path
        foreach ($sources as $source) {
            if (isset($this->routes['dynamic'][$source])) {
                $result = $this->checkLevel($this->routes['dynamic'][$source], explode('/', ltrim($path_normalized, '/')));

                if ($result !== null) {
                    if (isset($result['filters'])) {
                        $this->request->addFilters($result['filters']);
                    }

                    if (isset($result['params'])) {
                        foreach ($result['params'] as $param => $value) {
                            $this->request[$param] = $value;
                        }
                    }

                    return $result['controller'] ?? $default_controller;
                }
            }
        }

        // return default
        return $default_controller;
    }
}
