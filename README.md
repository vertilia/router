# router

A lightweight http routing library tuned for performance, allowing translation of request path into controller name and
validation of path parameters as defined in
[OpenAPI specification](http://spec.openapis.org/oas/v3.0.2#patterned-fields) and (partly) in
[URI Template](https://www.rfc-editor.org/rfc/rfc6570).

A highly efficient routing mechanism is especially useful in large routing tables since the number of operations to find
the controller depends on the routing tree levels and not on the total number of routes in the list.

Simply saying, instead of looking up all available paths represented as a list of regular expressions, we construct a
routing tree of folders from this list of paths and only visit the levels corresponding to current request. This leads
us step by step to the correct route instead of blindly scanning the whole list of available routes until the matching
route is found (or not). Also, the time to find incorrect routes is minimized, we don't need to scan the whole list to
find out that a route is missing.

People regularly researching their access logs to discover the most frequent requests to put them higher in routes list
will appreciate the functionality. These researches are now a history, and they may put their precious time on something
more important.

The router algorithm is universal, in a sense that when defining routing rules, you can attach any structure to the leaf
node, and it will be returned when corresponding route is provided. This leaf node is represented by an array containing
at least `controller` element with the name of operation that will be launched by the user-land code when the route is
found. If requested path contained parameters, they will be detected and injected in returned array as `parameters`
element.

Since we believe in high effectiveness of [`ValidArray`](https://github.com/vertilia/valid-array)-based
`HttpRequestInterface` structure in context of request handling, we also provide an `HttpRequestRouter` class allowing
automatic registration of route parameters in request object, their validation via filters provided in leaf elements and
easy access via array notation as in `$request['param']`. These parameters are guaranteed to be valid following the
user-specified format (see tests).

High efficiency of filtering mechanism is backed by php-native [`filter` extension](https://php.net/filter).

# Usage

## Universal router

When instantiating a universal router you need to pass the [`HttpParserInterface`](https://github.com/vertilia/parser)
parser object (which will translate the parameters placeholders to regexps) and a list of routing files.

```php
<?php // public/index.php (v1)

$router = new Vertilia\Router\Router(
    new Vertilia\Parser\OpenApiParser(),
    [__DIR__ . '/../etc/routes.php']
);
```

## HttpRequest router

In most cases you are working in CGI context, so you'll likely use an `HttpRequestRouter` class. This one will use
`OpenApiParser` by default, so you will not need to inject this one, but instead you will provide an `HttpRequest`,
from which the router will retrieve the route to lookup:

```php
<?php // public/index.php (v2)

$router = new Vertilia\Router\HttpRouter(
    new Vertilia\Request\HttpRequest(),
    [__DIR__ . '/../etc/routes.php']
);
```

## Routing file

Each routing file is a valid php script returning an array with routing information, where each entry specifies
a leaf node corresponding to a route. In its simplest form it's just a controller name for specific route:

```php
<?php // etc/routes.php

return [
    'GET /'              => App\Controller\IndexController::class,
    'GET /products'      => App\Controller\ProductsController::class,
    'GET /products/{id}' => App\Controller\ProductsController::class,
];
```

The more complex form may be used to provide filtering information for specific route, or any other information you will
need. In this case instead of a string with controller name an associative array is used. The only required element here
is `controller`, which will store the name of controller your code is looking for. Other elements may be defined as
convenient:
- `controller` - to provide controller name (used by both `Router` and `HttpRequestRouter`)
- `filters` - to provide filters for detected parameters (used by `HttpRequestRouter`)
- `responses` - to provide an array or responses per status code (for example to implement
  [OpenApi responses](https://swagger.io/docs/specification/describing-responses/))

```php
<?php // etc/routes.php

return [
    'GET /'              => App\Controller\IndexController::class,
    'GET /products'      => App\Controller\ProductsController::class,
    'GET /products/{id}' => [
        'controller' => App\Controller\ProductsController::class,
        'filters' => [
            'id' => FILTER_VALIDATE_INT,
        ],
    ],
    'PUT /products/{id}' => [
        'controller' => App\Controller\ProductsController::class,
        'filters' => [
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

This form (with `filters` element) is required if you use `HttpRequestRouter` version of the router, which uses filters
from leaf element to store and validate detected path parameters in `HttpRequest` object.

In the last route filters are provided not only for path parameter `id` but also for `description` and `image`
parameters that may come from other sources, like query, cookies, http body or headers. All of them will be filtered
accordingly and accessible inside the `HttpRequest` object in the form of, for example, `$request['description']`.

If filter for path parameter is not provided explicitly, `parameters` element will still be injected into the returned
leaf element containing all detected path parameters, but these parameters will not be validated nor registered in
`HttpResponse` object and user will need to validate their values by other means.

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

If the route could not be found in content type-aware form, it is searched in generic routes.

## Optimisation of routes parsing

When loading routing tables each route must be split to identify its method, path and MIME type (if present), then the
path is analyzed to distinguish static paths from paths with variables, and then paths with variables are replaced by
a tree structure where regular expressions allow to recognize parameters and catch variables values.

This parsing process is executed on each request, so when the number of routes is elevated, it may start to weight
considerably on performance.

To go faster, we can completely bypass the parsing stage on each request by pre-compiling the routing table and save
it in a native php file. Loading it on each request will take no time with active opcode caching.

To use pre-compiling method:

- use provided `vendor/bin/routec` route compiler script that takes a list of routes files, parses them and stores the
  resulting structure in a form understandable by the `Router::setParsedRoutes` method;
- save this structure to the `.php` file, ex: `routes-generated.php`;
- in your `index.php`, on router instantiation, omit the `$routes` parameter to the constructor and load pre-compiled
  routes tree via `$router->setParsedRoutes(include 'routes-generated.php')` instead.

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

use App\Controller\NotFoundController;
use Vertilia\Request\HttpRequest;
use Vertilia\Router\HttpRouter;

// construct route from the request
$request = new HttpRequest(
    $_SERVER,
    $_GET,
    $_POST,
    $_COOKIE,
    $_FILES,
    file_get_contents('php://input')
);

// instantiate HttpRouter without parsing
$router = new HttpRouter($request);

// set pre-compiled routes
$router->setParsedRoutes(include __DIR__ . '/../cache/routes-generated.php');

// get controller name and parameters from request
// use NotFoundController as default
$target = $router->getControllerFromRequest(NotFoundController::class);

// instantiate controller with request
$controller = new ($target['controller'])();

// let controller do its work and output corresponding response
$controller->render();
```

### Limitations of optimization techniques

Please be aware of the following caveats when going this way:

1. ⚠️ Php constants that you may use to define input filters (like `FILTER_VALIDATE_INT`, `FILTER_FLAG_HOST_REQUIRED`
   etc.) are normally exported as their numeric values. `routec` tool is trying to restore constants names from these
   values. These values may change from version to version of php binary, so try to generate the exported routes file
   using the same version that will be used with `setParsedRoutes()` call. `routec` will do its best trying to replace
   these numeric values by their respective constants in optimized routes file, but it's in your interests to verify the
   result in the optimized file. Pay special attention to flags sharing the same integer value,
   like `FILTER_FLAG_IPV4`, `FILTER_FLAG_HOSTNAME` and `FILTER_FLAG_EMAIL_UNICODE`, or flags not available in all php
   versions, like `FILTER_FLAG_GLOBAL_RANGE`.

2. ⚠️ Also, if you use validation callbacks (`FILTER_CALLBACK` or `ValidArray::FILTER_EXTENDED_CALLBACK` filters), they
   will not be exported at all, and you'll need to manually copy these callbacks from initial routes file.

# Sample `petstore.yaml` specification

API specification file for this example is available from
[OpenAPI](https://github.com/OAI/OpenAPI-Specification/blob/master/examples/v3.0/petstore-expanded.yaml) GitHub
repository.

Routing file corresponding to the specification is as follows:

```php
<?php // etc/routes.php

use Vertilia\ValidArray\ValidArray;

return [
    'GET /pets' => [
        'controller' => 'findPets',
        'filters' => [
            'limit' => FILTER_VALIDATE_INT,
            'tags'  => [
                'filter'  => ValidArray::FILTER_EXTENDED_CALLBACK,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => ['callback' => function($v) {return explode(',', $v);}],
            ],
        ],
    ],
    'POST /pets application/json' => [
        'controller' => 'addPet',
        'filters' => [
            'name' => FILTER_DEFAULT,
            'tag'  => FILTER_DEFAULT,
        ],
    ],
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

## Routing format reference

Recognized format:

- `[ROUTE => LEAF_STRUCTURE, …]`

ROUTE is a string containing 2 or 3 parts delimited by space character:
- `METHOD` `PATH`
- `METHOD` `PATH` `CONTENT_TYPE`

Parts of a ROUTE:
- `METHOD` is an HTTP request method
- `PATH` is an HTTP request path component which may contain `{variable}` placeholders representing path parameters
- `CONTENT_TYPE` is an HTTP request Content-Type header (if provided in request)

If Content-Type header is provided within a request, and corresponding 3-parts route is not found, search is repeated
with corresponding 2-parts route without `CONTENT_TYPE` part.

ROUTE examples:
- `GET /`
- `POST /api/login application-json`
- `GET /api/users/{id}/posts`

LEAF_STRUCTURE is returned when corresponding route is found. May be of 2 types:
- scalar, ex: `"UserResponse"`
- array, ex: `["controller" => "LoginResponse", ...other custom elements]`

If scalar form is used for LEAF_STRUCTURE (like `"UserResponse"`), it is translated internally during parsing phase into
an array with a single `controller` element: `["controller" => "UserResponse"]`. When LEAF_STRUCTURE is returned after
the route is identified, it is always returned as an array.

#### Examples of correct routes

```php
[
    "GET /" => "IndexResponse",
    "POST /api/users/me/login application-json" => "UserLoginResponse",
    "GET /api/users/{id}/posts" => [
        "controller" => "UserPostsResponse",
        "filters" => [
            "id" => FILTER_VALIDATE_INT
        ]
    ]
]
```

#### LEAF_STRUCTURE returned for corresponding requests

| Request METHOD PATH TYPE                    | Returned leaf structure                                                                                                                               |
|---------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------|
| `GET /`                                     | `["controller" => "IndexResponse"]`                                                                                                                   |
| `POST /api/users/me/login application-json` | `["controller" => "UserLoginResponse"]`                                                                                                               |
| `GET /api/users/42/posts`                   | <pre>[<br/>  "controller" => "UserPostsResponse",<br/>  "filters" => ["id" => FILTER_VALIDATE_INT],<br/>  "parameters" => ["id" => "42"],<br/>]</pre> |
