<?php
/**
 * Debug file to check routing
 */

// Custom autoloader
require_once __DIR__ . '/../autoload.php';

use App\Core\Router;

// Set base path
Router::setBasePath('/banka/public');

// Define test routes
Router::get('/', function() { echo "HOME"; });
Router::get('/register', function() { echo "REGISTER GET"; });
Router::post('/register', function() { echo "REGISTER POST"; });
Router::get('/login', function() { echo "LOGIN GET"; });
Router::post('/login', function() { echo "LOGIN POST"; });

// Get current request info
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = '/banka/public';

echo "<pre>";
echo "=== DEBUG INFO ===\n\n";
echo "REQUEST_METHOD: $method\n";
echo "REQUEST_URI: {$_SERVER['REQUEST_URI']}\n";
echo "Parsed URI: $uri\n";
echo "Base Path: $basePath\n";

// Simulate what dispatch does
if ($basePath && str_starts_with($uri, $basePath)) {
    $cleanUri = substr($uri, strlen($basePath));
} else {
    $cleanUri = $uri;
}
$cleanUri = '/' . trim($cleanUri, '/');
if ($cleanUri !== '/') {
    $cleanUri = rtrim($cleanUri, '/');
}

echo "Clean URI (after basePath removal): $cleanUri\n\n";

echo "=== REGISTERED ROUTES ===\n\n";
$routes = Router::getRoutes();
foreach ($routes as $i => $route) {
    echo "[$i] {$route['method']} {$route['path']}\n";
    echo "    Pattern: {$route['pattern']}\n";
    
    // Test if this route matches
    if ($route['method'] === $method && preg_match($route['pattern'], $cleanUri)) {
        echo "    ✅ MATCHES CURRENT REQUEST!\n";
    }
    echo "\n";
}

echo "</pre>";
