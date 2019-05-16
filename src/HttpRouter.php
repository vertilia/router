<?php
declare(strict_types=1);

namespace Vertilia\Router;

use InvalidArgumentException;
use Vertilia\Request\HttpRequestInterface;

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
     */
    public function __construct(HttpRequestInterface $request, $routes_path = null)
    {
        $this->request = $request;

        // set routes
        if (isset($routes_path)) {
            foreach ((array) $routes_path as $file) {
                $routes = include $file;
                if (!\is_array($routes)) {
                    throw new InvalidArgumentException(\sprintf(
                        'Routing table in %s must return an array',
                        $file
                    ));
                }

                $this->addRoutes($routes);
            }
        }
    }

    /**
     * Receives list of routes in format {"METHOD URI": "CONTROLLER", ...} or
     * ['METHOD URI', ...], then converts and registers the internal version in
     * $routes in the following format:
     * {"METHOD": {"URI_REGEX": "CONTROLLER_NAME", ...}, ...}
     * If controller name is not prodided, it is considered to be the path with
     * forward slashes translated to back slashes to separate namespaces.
     *
     * @param array $routes ex: {
     *  "GET /",
     *  "GET /one/way",
     *  "GET /another/way": "another\\way",
     *  "GET /v1/products/{id}": "App\\ProductsController"
     *  "GET /v2/users-{id}/friends": "App\\UsersController",
     * }
     * will register the following in $routes: {
     *  "GET": {
     *      "#^/$#": "index",
     *      "#^/one/way$#": "one\\way",
     *      "#^/another/way$#": "another\\way",
     *      "#^/v1/products/(?P<id>[^/]+)$#": "App\\ProductsController",
     *      "#^/v2/users-(?P<id>[^/]+)/friends$#": "App\\UsersController",
     *  }
     * }
     * @return RouterInterface
     */
    public function addRoutes(array $routes): RouterInterface
    {
        $struct = [];
        foreach ($routes as $k => $route) {
            if (\is_string($k)) {
                $controller = $route;
                $route = $k;
            } else {
                $controller = null;
            }

            list($method, $path) = \preg_split('/\s+/', "$route ");
            $path_normalized = Fs::normalizePath($path);
            $var = 0;
            $pattern = '#^/'.\preg_replace('/\{([[:alpha:]_]\w*)\}/', '(?P<$1>[^/]+)', $path_normalized, -1, $var).'$#';
            $ctr = $controller
                ?? ($path_normalized === ''
                    ? 'index'
                    : \preg_replace(['#[^\w/]+#', '#/#'], ['_', '\\'], $path_normalized)
                );
            if ($var) {
                $struct[$method]['regex'][$pattern] = $ctr;
            } else {
                $struct[$method]['static']["/$path_normalized"] = $ctr;
            }
        }

        $this->routes = \array_replace_recursive($this->routes, $struct);

        return $this;
    }

    public function getController(string $default_controller = null): ?string
    {
        $method = $this->request->getMethod();

        if (empty($this->routes)
            or ! \is_array($this->routes)
            or empty($this->routes[$method])
        ) {
            return $default_controller;
        }

        $path_normalized = '/' . Fs::normalizePath($this->request->getPath());

        if (isset($this->routes[$method]['static'])
            and isset($this->routes[$method]['static'][$path_normalized])
        ) {
            return $this->routes[$method]['static'][$path_normalized];
        }

        if (isset($this->routes[$method]['regex'])) {
            foreach ($this->routes[$method]['regex'] as $regex => $controller) {
                $m = null;
                if (\preg_match($regex, $path_normalized, $m)) {
                    foreach ($m as $k => $v) {
                        if (\is_string($k)) {
                            $this->request[$k] = $v;
                        }
                    }
                    return $controller;
                }
            }
        }

        return $default_controller;
    }
}
