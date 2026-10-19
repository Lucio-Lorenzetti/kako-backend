<?php

// Router para el servidor embebido de PHP en Wasmer Edge.
// Basado en vendor/laravel/framework/.../resources/server.php, pero sin
// depender de getcwd() (que en este entorno no apunta a /app/public).

require_once __DIR__.'/wasmer-polyfills.php';

$publicPath = '/app/public';

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

if ($uri !== '/' && file_exists($publicPath.$uri)) {
    return false;
}

require_once $publicPath.'/index.php';
