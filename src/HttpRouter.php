<?php

declare(strict_types=1);

namespace Vertilia\Router;

use Vertilia\Filesystem\Filesystem;
use Vertilia\Parser\OpenApiParser;
use Vertilia\Request\HttpRequest;

class HttpRouter extends Router
{
    protected HttpRequest $request;

    public function __construct(HttpRequest $request, ?array $routes_path = null)
    {
        parent::__construct(new OpenApiParser(), $routes_path);
        $this->request = $request;
    }

    public function getControllerFromRequest(?string $default_controller = null): ?array
    {
        // route from request
        $method = $this->request->getMethod();
        $path = '/' . Filesystem::normalizePath($this->request->getPath());
        $mime = $this->request->getHeaders()['content-type'] ?? null;

        $leaf_data = $this->getController(rtrim("$method $path $mime"), $default_controller);

        if (isset($leaf_data['filters'])) {
            $this->request->addFilters($leaf_data['filters']);
        }

        if (isset($leaf_data['parameters'])) {
            foreach ($leaf_data['parameters'] as $param => $value) {
                $this->request[$param] = $value;
            }
        }

        return $leaf_data;
    }
}
