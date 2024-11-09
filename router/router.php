<?php

namespace Router;

/**
 * The Router class
 * 
 * This class is responsible for routing requests to the appropriate handler.
 */
class Router
{
    private $routes = [];

    /**
     * Register a new route
     * 
     * Accepts a route method, path, and optional handler callback for custom routing logic. 
     * If no handler is provided, a default handler is used, and the path will be routed 
     * to a file with an identical path from the views directory, i.e `/about` routes 
     * to `views/about.php`.
     * 
     * @param string $method The HTTP method to match
     * @param string $path The path to match
     * @param callable $handler The handler to call if the route matches. Optional, defaults to a default handler
     */
    public function register($method, $path, $handler = null)
    {
        $path = rtrim($path, '/'); // Remove trailing slash

        // Check if the path has dynamic parameters
        if ($this->hasParams($path) && $handler !== null) {
            // Register a dynamic route
            $this->registerDynamic($method, $path, $handler);
            return;
        }
        // If no handler is provided, use the default handler
        if ($handler === null) {
            $handler = function () use ($path) {
                $this->defaultHandler($path);
            };
        }
        // Register the route
        $this->routes[$method][$path] = $handler;
    }

    /**
     * Register a new dynamic route
     * 
     * Accepts an HTTP method, path with dynamic segments wrapped in `{}`, 
     * and a handler callback function for custom routing logic.
     * 
     * Example: `$router->registerDynamic('GET', '/blog/{category}/{slug}', function($category, $slug) { ... });`
     * 
     * @param string $method The HTTP method to match
     * @param string $pattern The pattern to match
     * @param callable $handler The handler to call if the route matches. Should accept 
     * dynamic parameters as arguments and return true if the route is valid, and false 
     * if the route is invalid.
     */
    private function registerDynamic($method, $pattern, $handler)
    {
        $this->routes[$method][$pattern] = ['dynamic' => true, 'handler' => $handler];
    }

    /**
     * The default handler for static routes
     * 
     * This handler is called when no custom handler is provided for a route.
     * It will attempt to load a file with the same path from the views directory.
     * If the path is `/`, it will default to `views/home.php`.
     * 
     * @param string $path The path to handle
     */
    private function defaultHandler($path)
    {
        // Route root to home.php by default
        if ($path === "/" || $path === "") {
            $path = "/home";
        }

        // Load the view file if it exists
        $requiredPath = __DIR__ . "/../views" . $path . ".php";

        if (file_exists($requiredPath)) {
            require $requiredPath;
        } else {
            http_response_code(404);
        }
    }

    /**
     * Dispatch the router
     * 
     * This method will attempt to match the current request method and URI to a registered route.
     * If a route is found, the handler will be called. If no route is found, a 404 response will be sent.
     * 
     * This method can be called with custom request method and URI values for testing purposes.
     * If no values are provided, the method will use the current request method and URI from 
     * the $_SERVER superglobal.
     * 
     * @param string $requestMethod The request method to match against the routes array (optional)
     * @param string $requestUri The request URI to match against the routes array (optional)
     * 
     * @return void
     */
    public function dispatch($requestMethod = '', $requestUri = '')
    {
        if (empty($requestMethod)) {
            $requestMethod = $_SERVER['REQUEST_METHOD'];
        }
        if (empty($requestUri)) {
            $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        }
        $requestUri = rtrim($requestUri, '/'); // Remove trailing slash
        $requestUri = strtolower($requestUri);
        $parsedUrl = explode("/", parse_url($requestUri, PHP_URL_PATH));

        // Check static routes
        $staticPath = implode('/', $parsedUrl);
        if ($this->isValidStaticRoute($requestMethod, $staticPath)) {
            if (is_callable($this->routes[$requestMethod][$staticPath])) {
                return call_user_func($this->routes[$requestMethod][$staticPath]);
            }
            return call_user_func($this->routes[$requestMethod][$staticPath]['handler']);
        }

        // Try dynamic routes
        if (isset($this->routes[$requestMethod])) {
            foreach ($this->routes[$requestMethod] as $path => $route) {
                if ($this->isDynamicRouteRequest($route)) {
                    if ($this->matchDynamicRoute($path, $parsedUrl)) {
                        // Attempt to handle the route
                        return call_user_func_array($route['handler'], $this->getDynamicParams($path, $parsedUrl));
                    }
                }
            }
        }

        // If no valid route was found, send a 404
        http_response_code(404);
    }

    /**
     * Attempts to match a dynamic route to the current URL
     * 
     * @param string $pattern The pattern to match
     * @param array $urlParts The URL parts to match
     * 
     * @return bool
     */
    private function matchDynamicRoute($pattern, $urlParts)
    {
        // Split the pattern and URL into parts
        $patternParts = explode('/', $pattern);

        // If the parts don't match, return false
        if (count($patternParts) !== count($urlParts)) {
            return false;
        }

        foreach ($patternParts as $i => $part) {
            if ($this->isParam($part)) {
                continue;
            }
            if ($part !== $urlParts[$i]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a string is a dynamic parameter, wrapped in `{}` brackets
     * 
     * @param string $str The string to check
     * 
     * @return bool
     */
    private function isParam($str)
    {
        return strpos($str, '{') === 0 && strrpos($str, '}') === strlen($str) - 1;
    }

    /**
     * Get the dynamic parameters from a URL
     * 
     * @param string $pattern The pattern to match
     * @param array $urlParts The URL parts to match
     * 
     * @return array
     */
    private function getDynamicParams($pattern, $urlParts)
    {
        $params = [];
        $patternParts = explode('/', $pattern);

        foreach ($patternParts as $i => $part) {
            if ($this->isParam($part)) {
                // Sanitize the parameter
                $sanitizedParam = $this->sanitizeParam($urlParts[$i]);
                $params[] = $sanitizedParam;
            }
        }

        return $params;
    }

    /**
     * Sanitize the dynamic parameter
     * 
     * @param string $param The parameter to sanitize
     * 
     * @return string
     */
    private function sanitizeParam($param)
    {
        return htmlspecialchars(
            filter_var($param, FILTER_SANITIZE_URL), 
            ENT_QUOTES
        );
    }

    /**
     * Check if an incoming request matches a static route
     * 
     * @param string $requestMethod The request method
     * @param string $staticPath The static path to check
     * 
     * @return bool
     */
    private function isValidStaticRoute($requestMethod, $staticPath)
    {

        return isset($this->routes[$requestMethod][$staticPath])
            && (
                !is_array($this->routes[$requestMethod][$staticPath])
                || empty($this->routes[$requestMethod][$staticPath]['dynamic'])
            );
    }

    /**
     * Check if an incoming request is flagged as Dynamic
     * 
     * @param array $route The route to check
     * @return bool
     */
    private function isDynamicRouteRequest($route)
    {
        return is_array($route) && !empty($route['dynamic']);
    }

    /**
     * Check if the path of an incoming request has params, ex. /api/blogs/{id}
     * 
     * Checks if a string has opening bracket `{` followed by a closing bracket `}`.
     */
    private function hasParams($path)
    {
        return strpos($path, '{') !== false && strpos($path, '}') !== false && strpos($path, '{') < strpos($path, '}');
    }
}
