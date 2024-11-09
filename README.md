## Using the Simple PHP Router

Start by initializing the router:

_index.php_
```PHP
require_once 'router/router.php';

use Router\Router;

$router = new Router();

// Register the routes...

// Dispatch the router
$router->dispatch();
```

### Registering the routes
- You can add a route by calling the `register` method: `$router->register()`
    Which accepts three arguments:
    - `$method`: The first parameter is the HTTP method (e.g., GET, POST).
    - `$path`: The second parameter is the URL path (e.g., /about).
    - `$handler (optional)`: The third parameter is the handler callback that will be executed when the route is matched.

    #### Ex.
    ```PHP
    $router->register('GET', '/', function() {
        echo '<h1>Hello World! This is the homepage</h1>';
    });

    $router->register('GET', '/about', function() {
        echo '<h1>Hello World! This is the about page</h1>';
    });

    $router->register('GET', '/contact-us', function() {
        require __DIR__ . 'view/contact.php';
    });

    // Dispatch the router
    $router->dispatch();
    ```

    If no callback function is provided, the default callback will be used, which maps the registered path to a corresponding PHP file in a `views` directory with the same name as the path. The one exception is the root path `"/"`, which will automatically be routed to `/views/home.php`.

    #### Ex. 
    ```PHP
    $router->register('GET', '/about');
    ``` 
    will be routed automatically to `views/about.php`, and the root path

    ```PHP
    $router->register('GET', '/');
    ```
    will automatically be routed to `views/home.php`.


### Routes with dynamic params
- For routes with params, simple wrap the parameter in curly braces `{}` and then pass a parameter to accept a corresponding argument in the callback function.

    _Note: a callback function is required when registering a route with dynamic parameters_

    #### Ex. 

    ```PHP
    $router->register('GET', '/records/{id}', function($id) {
        echo '<h1>Hello World! This is record ' . $id . '</h1>';
    });

    // Performing further validation on params
    $router->register('GET', '/blog/page/{pageNumber}', function ($pageNumber) {
            if (is_numeric($pageNumber) && $pageNumber <= 10) {
                http_response_code(200);
                echo "<h1>You are viewing page {$pageNumber}</h1>";
            } else {
                http_response_code(404);
            }
        }
    );

    ```
    
### Creating an API Group

- The `registerAPIGroup` method allows you to register a group of API routes for a specific path and class. This method simplifies the process of setting up multiple routes for a single API resource.

    To use the `registerAPIGroup` method, call it with the base path for the API group and the fully qualified class name that contains the API methods.

    #### Ex. 
    ```PHP
    require_once 'router/router.php';
    require_once 'views/helloView.php';
    use Router\Router;

    $router = new Router();

    // Register an API group for the HelloView class
    $router->registerAPIGroup('/hello', '\App\HelloView');

    // Dispatch the router to handle the current request
    $router->dispatch();
    ```

    #### API Methods

    The class passed to `registerAPIGroup` should contain the following static methods:

    - `list()`: Handles GET requests to the base path.
    - `retrieve($pk)`: Handles GET requests to the base path with a primary key.
    - `doPost($pk)`: Handles POST requests to the base path with a primary key.
    - `doPut($pk)`: Handles PUT requests to the base path with a primary key.
    - `doDelete($pk)`: Handles DELETE requests to the base path with a primary key.
    - `doOptions()`: Handles OPTIONS requests to the base path.

    #### Example Class

    Here is an example class that can be used with `registerAPIGroup`:

    ```PHP
    <?php
    namespace App;

    class HelloView
    {
        public static function list()
        {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Hello, this is the list method!']);
        }

        public static function retrieve($pk)
        {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['message' => "Hello, this is the retrieve method with pk: $pk"]);
        }

        public static function doPost($pk)
        {
            $data = json_decode(file_get_contents('php://input'), true);
            $params = $_GET;
            http_response_code(201);
            header('Content-Type: application/json');
            echo json_encode(['message' => "Hello, this is the doPost method with pk: $pk", 'data' => $data, 'params' => $params]);
        }

        public static function doPut($pk)
        {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['message' => "Hello, this is the doPut method with pk: $pk"]);
        }

        public static function doDelete($pk)
        {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['message' => "Hello, this is the doDelete method with pk: $pk"]);
        }

        public static function doOptions()
        {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['message' => "Hello, this is the doOptions method"]);
        }
    }
    ```

### Dispatching the router with the request
- After registering all of your routes, make sure to dispatch the router via

```PHP
$router->dispatch();
```

The request is automatically handled by the method, but for testing purposes you can optionally pass in a $requestMethod and $requestUri