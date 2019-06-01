# router

A simple and efficient router library, allowing translation of request path into controller name and discovery of path
parameters as defined in [OpenAPI specification](http://spec.openapis.org/oas/v3.0.2#patterned-fields).

Use of [`ValidArray`](https://github.com/vertilia/valid-array)-based `HttpRequestInterface` allows for automatic
registration and validation of path parameters inside the request and later array-based access as in
`$request['param']`. These parameters are guaranteed to be valid following the specified parameter format (see below).

High efficiency of filtering mechanism is backed by php-native [`filter` extension](https://php.net/filter).

# Usage

When instantiating your router you need to pass the [`HttpRequestInterface`](https://github.com/vertilia/request)
request object (representing current request) and a list of routing files.

```php
<?php
// public/index.php
$request = new Vertilia\Request\HttpRequest($_SERVER, $_GET, $_POST, $_COOKIE);
$router = new Vertilia\Router\HttpRouter($request, [__DIR__ . '/../etc/routes.php']);
```

Each routing file is a valid php script returning an array with routing information, where each entry specifies
controller name for specific route.

```php
<?php
// etc/routes.php
return [
    'GET /'              => 'IndexController',
    'GET /products'      => 'ProductsController',
    'GET /products/{id}' => 'ProductsController',
];
```

A simpler form may be used (but not recommended) where the only required part is the route (space-delimited combination
of request method and URI) and controller is computed automatically by converting the URI to namespaced classname.

```php
<?php
// etc/routes.php
return [
    'GET /',
    'GET /products',
    'GET /products/{id}',
];
```

Controllers names defined in the above example are as follows:
- `"index"`
- `"products"`
- `"products\\_id_"`

The more complex form may be used to provide filtering information for specific route. In this case instead of a string
with controller name an array is used with the following keys:
- `"controller"` - to provide controller name
- `"filters"` - to provide filters for incoming parameters

```php
<?php
// etc/routes.php
return [
    'GET /'              => ['controller' => 'IndexController'],
    'GET /products'      => ['controller' => 'ProductsController'],
    'GET /products/{id}' => [
        'controller' => 'ProductsController',
        'filters'    => ['id' => FILTER_VALIDATE_INT],
    ],
    'PUT /products/{id}' => [
        'controller' => 'ProductsController',
        'filters'    => [
            'id' => [
                'filter'  => FILTER_VALIDATE_INT,
                'options' => ['min_range' => 1],
            ],
            'description' => FILTER_SANITIZE_STRING,
            'image' => [
                'filter' => FILTER_VALIDATE_URL,
                'flags'  => FILTER_FLAG_HOST_REQUIRED,
            ],
        ],
    ],
];
```

In the last route filters are provided not only for path parameter `id` but also for `description` and `image`
parameters that may come from query, cookies or http headers.
