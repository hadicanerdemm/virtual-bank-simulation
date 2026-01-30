<?php
declare(strict_types=1);

namespace App\Config;

/**
 * Environment Configuration Handler
 * Loads and manages .env file variables (Composer-free version)
 */
class Environment
{
    private static ?Environment $instance = null;
    private array $variables = [];
    private bool $loaded = false;

    private function __construct()
    {
        $this->load();
    }

    public static function getInstance(): Environment
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Parse .env file manually (no Composer required)
     */
    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $basePath = dirname(__DIR__, 2);
        
        // Check if .env exists, if not copy from .env.example
        if (!file_exists($basePath . '/.env') && file_exists($basePath . '/.env.example')) {
            copy($basePath . '/.env.example', $basePath . '/.env');
        }

        // Parse .env file manually
        $envFile = $basePath . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                // Skip comments
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }
                
                // Parse KEY=value
                if (strpos($line, '=') !== false) {
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Remove quotes
                    if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                        (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                        $value = substr($value, 1, -1);
                    }
                    
                    $this->variables[$key] = $value;
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }

        $this->loaded = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->variables[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }

    public function getString(string $key, string $default = ''): string
    {
        return (string) $this->get($key, $default);
    }

    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);
        
        if (is_bool($value)) {
            return $value;
        }
        
        return in_array(strtolower((string) $value), ['true', '1', 'yes', 'on'], true);
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        return (float) $this->get($key, $default);
    }

    public function isProduction(): bool
    {
        return $this->getString('APP_ENV', 'development') === 'production';
    }

    public function isDevelopment(): bool
    {
        return $this->getString('APP_ENV', 'development') === 'development';
    }

    public function isDebug(): bool
    {
        return $this->getBool('APP_DEBUG', true);
    }
}

/**
 * Helper function to get environment variables
 */
function env(string $key, mixed $default = null): mixed
{
    return Environment::getInstance()->get($key, $default);
}
