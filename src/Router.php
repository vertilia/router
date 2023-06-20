<?php

declare(strict_types=1);

namespace Vertilia\Router;

use Vertilia\Filesystem\Filesystem;
use Vertilia\Parser\ParserInterface;

class Router implements RouterInterface
{
    protected ?ParserInterface $parser;
    protected array $routes = [];

    /**
     * When array of files is provided, latter entries overwrite existing ones
     *
     * @param ?ParserInterface $parser
     * @param ?array $routes_path path to configuration file (or a list of configuration files to load)
     */
    public function __construct(?ParserInterface $parser = null, ?array $routes_path = null)
    {
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
     * @return ?ParserInterface
     */
    public function getParser(): ?ParserInterface
    {
        return $this->parser;
    }

    /**
     * Receive a list of routes, parse it and register the internal version of route tree in $routes.
     *
     * @param array $routes
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
            if ('' !== $mime) {
                $method .= " $mime";
            } elseif ('' === $path) {
                $path = $method;
                $method = 'GET';
            }

            if (false !== strpos($path, '{')) {
                $path_dirs = explode('/', trim($path, '/'));
                if (!isset($tree['dynamic'][$method])) {
                    $tree['dynamic'][$method] = [];
                }
                $r = &$tree['dynamic'][$method];
                foreach ($path_dirs as $dir) {
                    if (false !== strpos($dir, '{')) {
                        $pattern = $this->parser->getRegex($dir);
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
                    $subtree[$dir]['result/']['parameters'] = $params;
                    return $subtree[$dir]['result/'];
                }
            } else {
                $result = $this->checkLevel($subtree[$dir], $path_dirs, $params, ++$level);
                if (null !== $result) {
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
                            $next['result/']['parameters'] = $params + $tmp_params;
                            return $next['result/'];
                        }
                    } else {
                        $result = $this->checkLevel($next, $path_dirs, $params + $tmp_params, ++$level);
                        if (null !== $result) {
                            return $result;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Return leaf structure from the routing table, inject parameters if detected. Return default controller if route
     * was not identified.
     *
     * @return array leaf structure corresponding to the found route. may add "parameters" element if detected
     */
    public function getController(string $route, ?string $default_controller = null): array
    {
        if (empty($this->routes)) {
            return ['controller' => $default_controller];
        }

        list($method, $x, $mime) = explode(' ', "$route  ", 4);
        $sources = $mime ? ["$method $mime", $method] : [$method];

        $path_normalized = Filesystem::normalizePath($x);
        $slash_path_normalized = "/$path_normalized";

        // check static path
        if (isset($this->routes['static']["$method $mime"][$slash_path_normalized])) {
            return $this->routes['static']["$method $mime"][$slash_path_normalized];
        } elseif (isset($this->routes['static'][$method][$slash_path_normalized])) {
            return $this->routes['static'][$method][$slash_path_normalized];
        }

        // check dynamic path
        foreach ($sources as $source) {
            if (isset($this->routes['dynamic'][$source])) {
                $result = $this->checkLevel($this->routes['dynamic'][$source], explode('/', $path_normalized));

                if (null !== $result) {
                    return $result;
                }
            }
        }

        // return default
        return ['controller' => $default_controller];
    }
}
