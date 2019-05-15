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
    /** @var string */
    protected $controller;

    /**
     * When array of files is provided, latter entries overwrite existing ones
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
            $pattern = '#^/'.\preg_replace('/\{([[:alpha:]_]\w*)\}/', '(?P<$1>[^/]+)', $path_normalized).'$#';
            $struct[$method][$pattern] = isset($controller)
                ? $controller
                : ($path_normalized === ''
                    ? 'index'
                    : \strtr(\preg_replace(
                        ['#\{[[:alpha:]_]\w*\}#', '#[^\w/]+#', '#//+#', '#_?/_?#', '#^[/_]+#', '#[/_]+$#'],
                        ['', '_', '/', '/', '', ''],
                        $path_normalized
                    ), '/', '\\')
                );
        }

        $this->routes = \array_replace_recursive($this->routes, $struct);

        return $this;
    }

    /**
     * Tries to match $http_path in $routes and set $controller to
     * corresponding name if found.
     * @return bool whether $controller was set
     */
    public function parseRoute(): bool
    {
        $this->controller = null;

        if (empty($this->routes)
            or ! \is_array($this->routes)
            or empty($this->routes[$this->request->getMethod()])
        ) {
            return false;
        }

        $path = '/' . Fs::normalizePath($this->request->getPath());

        foreach ($this->routes[$this->request->getMethod()] as $regex => $controller) {
            $m = null;
            if (\preg_match($regex, $path, $m)) {
                $this->controller = $controller;
                foreach ($m as $k => $v) {
                    if (\is_string($k)) {
                        $this->request[$k] = $v;
                    }
                }
                return true;
            }
        }

        return false;
    }

    public function getResponseController(string $default_controller = null): ?string
    {
        return $this->controller ?: $default_controller;
    }
}
