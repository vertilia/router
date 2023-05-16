# router

A lightweight http routing library tuned for performance, allowing translation of request path into controller name and
validation of path parameters as defined in
[OpenAPI specification](http://spec.openapis.org/oas/v3.0.2#patterned-fields).

A highly efficient routing mechanism is especially useful in large routing tables since the number of operations to find
the controller depends on the routing tree levels and not on the total number of routes in the list.

Simply saying, instead of looking up all the paths represented as a list of regular expressions, we construct a routing
tree of folders from this list of paths and only investigate the levels corresponding to current request. This leads us
step by step to the correct route instead of blindly scanning the whole list of available routes until the matching
route is found (or not). Also, the time to find incorrect routes is minimized, we don't need to scan the whole list to
find out that a route is missing.

People regularly researching their access logs to discover the most frequent requests to put them higher in routes list
will appreciate the functionality. These researches are now a history, and they may put their precious time on something
more important.

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

Yes, you need to implement the controllers and make them available via `composer` autoloader or other mechanism. This is
out of scope of routing library, but we have a real-world example of router use [below](#example).

Also, it's up to you to decide in which form controller names are provided to the application, whether it is a class
name as we use in our examples, or method names, or function names, or even a partial string that will be completed
later. Implement it the way you like. We prefer the method described above, since it has several advantages. Class names
referencable via `::class` constants make it simpler to type using IDE code completion. Also, they are more error-prone,
since renaming a class with your IDE will either automatically rename the controller name in route file or at least
display it as non-existent in there. You will not need to wait the integration or even deployment phase to discover the
undefined exception. And yes, the optimisation phase described below will convert it to strings anyway.

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

- use provided `vendor/bin/routec` route compiler script that takes a list of routes files, parses them and stores in
  form understandable by the `HttpRouter::setParsedRoutes` method;
- save this structure to the `.php` file, ex: `routes-generated.php`;
- in your `index.php`, on router instantiation, omit the `$parser` and `$routes` parameters to the `HttpRouter`
  constructor and set pre-compiled routes tree via `$router->setParsedRoutes(include 'routes-generated.php')` instead.

### Example

This script needs to be executed every time the routes file is updated to translate `etc/routes.php` file into
`cache/routes-generated.php`:

```shell
vendor/bin/routec etc/routes.php >cache/routes-generated.php
```

You may provide several input files if your routes are split between them. `routec` tool will output a final file with
all routes combined.

On each request we don't need to parse the whole list of routes since we use already cached structure from
`cache/routes-generated.php`:

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
$router->setParsedRoutes(include __DIR__ . '/../cache/routes-generated.php');

// set filtered variables in request and get controller name from the router
// using NotFoundController as default
$controller_name = $router->getController(App\Controller\NotFoundController::class);

// instantiate controller with request
$controller = new $controller_name($router->getRequest());

// let controller do its work and output corresponding response
$controller->processRequest();
```

### Limitations of optimization techniques

Please be aware of the following caveats when going this way:

1. Php constants that you may use to define input filters (like `FILTER_VALIDATE_INT`, `FILTER_FLAG_HOST_REQUIRED` etc.)
   are normally exported as their numeric values. These values may change from version to version of php binary, so try
   to generate the exported routes file using the same version that will be used with `setParsedRoutes()` call. `routec`
   will do its best trying to replace these numeric values by their respective constants in optimized routes file, but
   it's in your interests to verify the result in the optimized file. Pay special attention to flags sharing the same
   integer value, like `FILTER_FLAG_IPV4`, `FILTER_FLAG_HOSTNAME` and `FILTER_FLAG_EMAIL_UNICODE`, or flags not
   available in all php versions, like `FILTER_FLAG_GLOBAL_RANGE`.

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
