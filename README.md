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
parameters that may come from other sources, like query, cookies, http body or headers.

## Providing content MIME type

It is also possible to provide content MIME type when defining the route, like in
```
GET /products/{id} application/json
```
to be able to set different controllers and validations for different types. In this case content type-specific routes
take precedence over generic routes, like in
```
GET /products/{id}
```

## Optimisation routes parsing

When loading routing tables each route must be split to identify its method, path and MIME type (if present), then the
path is analyzed to distinguish static paths from paths with variables, and then paths with variables are replaced by
regular expressions allowing to recognise the path and catch variables values in one operation.

This process is executed on each request, so when the number of routes is elevated, it may start to weight considerably
on performance.

We can bypass the process of parsing the route if we provide each route method, path and MIME type right in route
description. This is possible with array-based form for route description and the following additional keys:
- `"method"` - defines route method (GET if omitted)
- `"path-static"` - defines route path as static (mutualy exclusive with `"path-regex"`)
- `"path-regex"` - defines route path as regex (mutualy exclusive with `"path-static"`)
- `"mime-type"` - defines request MIME type (any if omitted)

When using this form of route description we cannot specify route as a key, since it will overwrite the value specified
as `"path-static"` or `"path-regex"`.

Example:

```php
<?php
// etc/routes.php
return [
    // ...
    [
        'method' => 'GET',
        'path-static' => '/products',
        'controller'  => 'ProductsController',
    ],
    [
        'path-regex' => '#^/products/(?P<id>.+)$#',
        'mime-type'  => 'application/json',
        'controller' => 'ProductsController',
        'filters'    => ['id' => ['filter' => FILTER_VALIDATE_INT]],
    ],
];
```

Here we declare one static and one regex-based routes (similar to previous example but with addition of `"mime-type"`).
Please note that regular expression uses specific notation to extract the variable name and value. They will be
registered in HttpRequest object if corresponding filter is set.

# Sample petstore.yaml specification

API specification file is vailable from
[OpenAPI](https://github.com/OAI/OpenAPI-Specification/blob/master/examples/v3.0/petstore-expanded.yaml) github
repository.

Routing file corresponding to the specification is as follows:

```php
<?php
// etc/routes.php
return [
    'GET /pets' => [
        'controller' => 'findPets',
        'filters' => [
            'tags' => [
                'filter' => FILTER_CALLBACK,
                'options' => function($v){return is_string($v) ? explode(',', $v) : false;},
            ],
            'limit' => FILTER_VALIDATE_INT,
        ],
    ],
    'POST /pets application/json' => [
        'controller' => 'addPet',
        'filters' => [
            'name' => FILTER_DEFAULT,
            'tag' => FILTER_DEFAULT,
        ],
    ]

    'GET /pets/{id}' => [
        'controller' => 'find_pet_by_id',
        'filters' => [
            'id' => FILTER_VALIDATE_INT,
        ],
    ],
    'DELETE /pets/{id}' => [
        'controller' => 'deletePet',
        'filters' => [
            'id' => FILTER_VALIDATE_INT,
        ],
    ],
];
```
