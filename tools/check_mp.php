<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/../vendor/autoload.php';
$controllerPath = __DIR__ . '/../app/Http/Controllers/Api/MercadoPagoController.php';

echo "Controller file path: $controllerPath" . PHP_EOL;
echo "Exists: " . (file_exists($controllerPath) ? 'yes' : 'no') . PHP_EOL;

echo "Declared classes before: " . PHP_EOL;
foreach (get_declared_classes() as $c) {
    if (str_starts_with($c, 'App\\Http\\Controllers')) echo "  $c" . PHP_EOL;
}

try {
    require_once $controllerPath;
    echo "\nIncluded controller file ok.\n";
} catch (Throwable $e) {
    echo "Require error: " . $e->getMessage() . PHP_EOL;
}

echo "\nDeclared classes after: " . PHP_EOL;
foreach (get_declared_classes() as $c) {
    if (str_starts_with($c, 'App\\Http\\Controllers')) echo "  $c" . PHP_EOL;
}

echo "\nclass_exists App\\Http\\Controllers\\Api\\MercadoPagoController: " . (class_exists('App\\Http\\Controllers\\Api\\MercadoPagoController') ? 'yes' : 'no') . PHP_EOL;
echo "class_exists App\\Http\\Controllers\\MercadoPagoController: " . (class_exists('App\\Http\\Controllers\\MercadoPagoController') ? 'yes' : 'no') . PHP_EOL;

// show first 60 lines of file for quick sanity
echo "\n---- File head (first 60 lines) ----\n";
$lines = @file($controllerPath);
if ($lines) {
    $head = array_slice($lines, 0, 60);
    foreach ($head as $i => $ln) {
        printf("%4d: %s", $i+1, $ln);
    }
} else {
    echo "(could not read file)\n";
}

