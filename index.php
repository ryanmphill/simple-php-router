<?php

require_once 'router/router.php';

use Router\Router;

$router = new Router();

$router->register('GET', '/', function() {
    echo 'Hello World! This is the homepage';
});

$router->register('GET', '/about', function() {
    echo 'Hello World! This is the about page';
});

$router->register('GET', '/resources/{name}', function($name) {
    echo "Hello World! This is the $name page";
});

// Dispatch the router to handle the current request
$router->dispatch();
