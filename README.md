# router

Speed-optimized http routing library, allowing translation of request path into controller name and discovery of path
parameters as defined in [OpenAPI specification](http://spec.openapis.org/oas/v3.0.2#patterned-fields).

A highly efficient routing mechanism is especially useful in large routing tables where the number of operations to find
the route is function of the routing tree levels and not of the total number of routes in the list.

Simply saying, instead of looking up all the paths represented as regular expressions, we construct a routing tree of
folders from the paths and only investigate the levels corresponding to current request. This leads us step by step to
the correct route instead of blindly scanning the whole list of available routes until the route is found (or not).

Use of [`ValidArray`](https://github.com/vertilia/valid-array)-based `HttpRequestInterface` allows for automatic
registration and validation of path parameters inside the request and later array-based access as in
`$request['param']`. These parameters are guaranteed to be valid following the specified parameter format (see below).

High efficiency of filtering mechanism is backed by php-native [`filter` extension](https://php.net/filter).

# Usage

When instantiating your router you need to pass the [`HttpRequestInterface`](https://github.com/vertilia/request)
request object (representing current request), the [`HttpParserInterface`](https://github.com/vertilia/parser) parser
object (which will translate the routes to regexps) and a list of routing files.

```php
<?php // public/index.php

$request = new Vertilia\Request\HttpRequest($_SERVER);
$parser = new Vertilia\Parser\OpenApiParser();
$router = new Vertilia\Router\HttpRouter($request, $parser, [__DIR__ . '/../etc/routes.php']);
```

Each routing file is a valid php script returning an array with routing information, where each entry specifies
controller name for specific route.

```php
<?php // etc/routes.php

return [
    'GET /'              => App\Controller\IndexController::class,
    'GET /products'      => App\Controller\ProductsController::class,
    'GET /products/{id}' => App\Controller\ProductsController::class,
];
```

The more complex form may be used to provide filtering information for specific route. In this case instead of a string
with controller name an array is used with the following keys:
- `"controller"` - to provide controller name
- `"filters"` - to provide filters for incoming parameters

```php
<?php // etc/routes.php

return [
    'GET /'              => App\Controller\IndexController::class,
    'GET /products'      => App\Controller\ProductsController::class,
    'GET /products/{id}' => [
        'controller' => App\Controller\ProductsController::class,
        'filters'    => ['id' => FILTER_VALIDATE_INT],
    ],
    'PUT /products/{id}' => [
        'controller' => App\Controller\ProductsController::class,
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
parameters that may come from other sources, like query, cookies, http body or headers. All of them will be filtered
accordingly and accessible inside the `HttpRequest` object in the form of, for example, `$request['description']`.

If filter for path parameter is not provided explicitly, `FILTER_DEFAULT` is used by default when a parameter is found
in path.

Yes, you need to implement the controllers and make them available via `composer` or other mechanism. This is out of
scope of routing library, but we have a real-world example of router use below.

## Providing content MIME type

To be able to set different controllers and validations for different incoming content types it is also possible to
provide content MIME type when defining the route, like in:
```
GET /products/{id} application/json
```
In this case content type-specific routes take precedence over generic routes, like in
```
GET /products/{id}
```

## Optimisation of routes parsing

When loading routing tables each route must be split to identify its method, path and MIME type (if present), then the
path is analyzed to distinguish static paths from paths with variables, and then paths with variables are replaced by
regular expressions allowing to recognize the path and catch variables values in one operation.

This process is executed on each request, so when the number of routes is elevated, it may start to weight considerably
on performance.

To go faster, we can completely bypass the parsing stage on each request with pre-compiling the routing table and keep
it in a native php file. Loading it on each request will take no time with active opcode caching.

To use pre-compiling method:

- use provided `bin/routec` route compiler script that takes all your known routes files, parses them and stores in form
understandable by the `HttpRouter` class;
- store this structure to the `.php` file;
- on router instantiation, omit the `$parser` and `$routes` parameters to the constructor and set pre-compiled routes
tree via `$router->setParsedRoutes(include 'routes-optimized.php')` instead.

### Example

This script needs to be executed once to translate `etc/routes.php` file into `cache/http-routes-generated.php`:

```shell
vendor/vertilia/router/bin/routec etc/routes.php >cache/http-routes-generated.php
```

On each request we don't need to parse the whole list of routes since we use already cached structure from
`cache/http-routes-generated.php`:

```php
<?php // www/index.php

require __DIR__ . '/../vendor/autoload.php';

use Vertilia\Request\HttpRequest;
use Vertilia\Router\HttpRouter;

// instantiate HttpRouter without parsing
$router = new HttpRouter(new HttpRequest(
    $_SERVER,
    $_GET,
    $_POST,
    $_COOKIE,
    $_FILES,
    file_get_contents('php://input')
));

// set pre-compiled routes
$router->setParsedRoutes(include __DIR__ . '/../cache/http-routes-generated.php');

// set filtered variables in request and get controller name from the router
// using NotFoundController as default
$controller_name = $router->getController(App\Controller\NotFoundController::class);

// instantiate controller with request
$controller = new $controller_name($router->getRequest());

// let controller do its work and output corresponding response
$controller->processRequest();
```

### Limitations of optimization techniques

Please be aware about the following specialties when going this way:

1. Php constants that you may use to define input filters (like `FILTER_VALIDATE_INT`, `FILTER_FLAG_HOST_REQUIRED` etc.)
will be exported as their numeric values. These values may change from version to version of php binary, so try to
generate the exported routes file using the same version that will be used with `setParsedRoutes()` call. `routec` will
do its best trying to replace these numeric values by their respective constants in optimized routes file, but it's in
your interests to verify the result manually at least once. Pay special attention to flags sharing the same integer
value, like `FILTER_FLAG_IPV4`, `FILTER_FLAG_HOSTNAME` and `FILTER_FLAG_EMAIL_UNICODE`, or flags not available in all
php versions, like `FILTER_FLAG_GLOBAL_RANGE`.

2. Also, if you use validation callbacks (`FILTER_CALLBACK` filter), they will not be exported at all, and you'll need
to manually copy these callbacks from initial routes file.

# Sample `petstore.yaml` specification

API specification file for this example is available from
[OpenAPI](https://github.com/OAI/OpenAPI-Specification/blob/master/examples/v3.0/petstore-expanded.yaml) github
repository.

Routing file corresponding to the specification is as follows:

```php
<?php // etc/routes.php

return [
    'GET /pets' => [
        'controller' => 'findPets',
        'filters'    => [
            'limit' => FILTER_VALIDATE_INT,
            'tags'  => [
                'filter'  => FILTER_CALLBACK,
                'options' => function($v) {return explode(',', $v);},
            ],
        ],
    ],
    'POST /pets application/json' => [
        'controller' => 'addPet',
        'filters'    => [
            'name' => FILTER_DEFAULT,
            'tag'  => FILTER_DEFAULT,
        ],
    ],
    'GET /pets/{id}' => [
        'controller' => 'find_pet_by_id',
        'filters'    => [
            'id' => FILTER_VALIDATE_INT,
        ],
    ],
    'DELETE /pets/{id}' => [
        'controller' => 'deletePet',
        'filters'    => [
            'id' => FILTER_VALIDATE_INT,
        ],
    ],
];
```
