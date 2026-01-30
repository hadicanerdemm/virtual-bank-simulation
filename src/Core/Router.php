<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Simple and flexible router for API and web routes
 */
class Router
{
    private static array $routes = [];
    private static array $middlewares = [];
    private static string $basePath = '';
    private static ?string $namespace = null;
    private static array $groupStack = [];

    /**
     * Register GET route
     */
    public static function get(string $path, callable|array|string $handler): void
    {
        self::addRoute('GET', $path, $handler);
    }

    /**
     * Register POST route
     */
    public static function post(string $path, callable|array|string $handler): void
    {
        self::addRoute('POST', $path, $handler);
    }

    /**
     * Register PUT route
     */
    public static function put(string $path, callable|array|string $handler): void
    {
        self::addRoute('PUT', $path, $handler);
    }

    /**
     * Register DELETE route
     */
    public static function delete(string $path, callable|array|string $handler): void
    {
        self::addRoute('DELETE', $path, $handler);
    }

    /**
     * Register PATCH route
     */
    public static function patch(string $path, callable|array|string $handler): void
    {
        self::addRoute('PATCH', $path, $handler);
    }

    /**
     * Add route with method
     */
    private static function addRoute(string $method, string $path, callable|array|string $handler): void
    {
        $prefix = self::getGroupPrefix();
        $middlewares = self::getGroupMiddlewares();
        
        // Don't include basePath here - it's stripped from URI during dispatch
        $fullPath = $prefix . $path;
        $fullPath = '/' . trim($fullPath, '/');
        
        if ($fullPath !== '/') {
            $fullPath = rtrim($fullPath, '/');
        }

        self::$routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'handler' => $handler,
            'middlewares' => $middlewares,
            'pattern' => self::convertToRegex($fullPath)
        ];
    }

    /**
     * Route grouping for prefixes and middlewares
     */
    public static function group(array $options, callable $callback): void
    {
        self::$groupStack[] = $options;
        $callback();
        array_pop(self::$groupStack);
    }

    /**
     * Get current group prefix
     */
    private static function getGroupPrefix(): string
    {
        $prefix = '';
        foreach (self::$groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
        }
        return $prefix;
    }

    /**
     * Get current group middlewares
     */
    private static function getGroupMiddlewares(): array
    {
        $middlewares = [];
        foreach (self::$groupStack as $group) {
            if (isset($group['middleware'])) {
                $mw = is_array($group['middleware']) ? $group['middleware'] : [$group['middleware']];
                $middlewares = array_merge($middlewares, $mw);
            }
        }
        return $middlewares;
    }

    /**
     * Convert route path to regex pattern
     */
    private static function convertToRegex(string $path): string
    {
        // Convert {param} to named capture group
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        
        // Convert {param?} to optional named capture group
        $pattern = preg_replace('/\{([a-zA-Z_]+)\?\}/', '(?P<$1>[^/]*)?', $pattern);
        
        return '#^' . $pattern . '$#';
    }

    /**
     * Register global middleware
     */
    public static function middleware(string $name, callable|string $handler): void
    {
        self::$middlewares[$name] = $handler;
    }

    /**
     * Set base path (for subdirectory installations)
     */
    public static function setBasePath(string $path): void
    {
        self::$basePath = '/' . trim($path, '/');
    }

    /**
     * Dispatch the current request
     */
    public static function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Remove base path if set
        if (self::$basePath && str_starts_with($uri, self::$basePath)) {
            $uri = substr($uri, strlen(self::$basePath));
        }
        
        // Normalize URI
        $uri = '/' . trim($uri, '/');
        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }

        // Handle preflight requests
        if ($method === 'OPTIONS') {
            self::handleCors();
            http_response_code(200);
            exit;
        }

        // Find matching route
        foreach (self::$routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                // Extract route parameters
                $params = array_filter($matches, fn($key) => !is_numeric($key), ARRAY_FILTER_USE_KEY);
                
                // Run middlewares
                foreach ($route['middlewares'] as $middleware) {
                    $middlewareHandler = self::$middlewares[$middleware] ?? null;
                    if ($middlewareHandler) {
                        $result = self::callHandler($middlewareHandler, $params);
                        if ($result === false) {
                            return;
                        }
                    }
                }
                
                // Call route handler
                self::handleCors();
                self::callHandler($route['handler'], $params);
                return;
            }
        }

        // No route found
        self::handleCors();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'NOT_FOUND',
                'message' => 'Endpoint not found'
            ]
        ]);
    }

    /**
     * Call a handler (callable, class@method, or [class, method])
     */
    private static function callHandler(callable|array|string $handler, array $params = []): mixed
    {
        if (is_callable($handler)) {
            return call_user_func_array($handler, $params);
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler);
            $controller = new $class();
            return call_user_func_array([$controller, $method], $params);
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            if (is_string($class)) {
                $class = new $class();
            }
            return call_user_func_array([$class, $method], $params);
        }

        throw new \InvalidArgumentException('Invalid route handler');
    }

    /**
     * Handle CORS headers
     */
    private static function handleCors(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Key, X-Idempotency-Key');
        header('Access-Control-Max-Age: 86400');
    }

    /**
     * Get all registered routes (for debugging)
     */
    public static function getRoutes(): array
    {
        return self::$routes;
    }

    /**
     * Clear all routes (for testing)
     */
    public static function clear(): void
    {
        self::$routes = [];
        self::$middlewares = [];
        self::$groupStack = [];
    }
}
