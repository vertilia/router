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
     * Receives list of routes in format {"METHOD URI": "CONTROLLER", ...}
     * converts and registers the internal version in $routes in format
     * {"METHOD": {"URI_REGEX": "CONTROLLER_NAME", ...}, ...}
     *
     * @param array $routes ex: {
     *  "GET /": "IndexController",
     *  "GET /v1/products/{id}": "ProductsController"
     * }
     * will register the following in $routes: {
     *  "GET": {
     *      "#^/$#": "IndexController",
     *      "#^/v1/products/(?P<id>[^/]+)$#": "ProductsController",
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
            $preg_parts = [];
            $dir_parts = [];
            foreach (\explode('/', Fs::normalizePath($path)) as $path_part) {
                $m = [];
                if (\substr($path_part, 0, 1) == '{'
                    and \preg_match('#^\{([[:alpha:]_]\w*)\}$#', $path_part, $m)
                ) {
                    $preg_parts[] = "(?P<{$m[1]}>[^/]+)";
                } elseif (\strlen($path_part)) {
                    $preg_parts[] = \preg_quote($path_part, '#');
                    $dir_parts[] = $path_part;
                }
            }
            $struct[$method]['#^/'.\implode('/', $preg_parts).'$#'] = isset($controller)
                ? $controller
                : ($dir_parts ? \implode('\\', $dir_parts) : null);
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
