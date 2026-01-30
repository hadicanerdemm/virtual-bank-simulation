<?php
declare(strict_types=1);

namespace App\Core;

/**
 * HTTP Request Handler
 */
class Request
{
    private array $query;
    private array $post;
    private array $server;
    private array $headers;
    private ?array $json = null;

    public function __construct()
    {
        $this->query = $_GET;
        $this->post = $_POST;
        $this->server = $_SERVER;
        $this->headers = $this->parseHeaders();
    }

    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($this->server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[strtolower($name)] = $value;
            }
        }
        return $headers;
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function uri(): string
    {
        return parse_url($this->server['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    }

    public function query(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    public function post(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->post;
        }
        return $this->post[$key] ?? $default;
    }

    public function json(string $key = null, mixed $default = null): mixed
    {
        if ($this->json === null) {
            $content = file_get_contents('php://input');
            $this->json = json_decode($content, true) ?? [];
        }
        
        if ($key === null) {
            return $this->json;
        }
        return $this->json[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->json($key) ?? $this->post($key) ?? $this->query($key) ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->post, $this->json() ?? []);
    }

    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization');
        if ($auth && str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    public function ip(): string
    {
        return $this->server['HTTP_X_FORWARDED_FOR'] 
            ?? $this->server['HTTP_CLIENT_IP'] 
            ?? $this->server['REMOTE_ADDR'] 
            ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    public function isAjax(): bool
    {
        return strtolower($this->header('x-requested-with', '')) === 'xmlhttprequest';
    }

    public function isJson(): bool
    {
        return str_contains($this->header('content-type', ''), 'application/json');
    }

    public function expectsJson(): bool
    {
        return str_contains($this->header('accept', ''), 'application/json');
    }
}

/**
 * HTTP Response Handler
 */
class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private mixed $content = null;

    public function status(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function header(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function json(array $data, int $status = null): void
    {
        if ($status !== null) {
            $this->statusCode = $status;
        }
        
        $this->header('Content-Type', 'application/json');
        $this->send(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function success(mixed $data = null, string $message = null, int $status = 200): void
    {
        $response = ['success' => true];
        
        if ($message !== null) {
            $response['message'] = $message;
        }
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        $this->json($response, $status);
    }

    public function error(string $code, string $message, int $status = 400, array $details = null): void
    {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ];
        
        if ($details !== null) {
            $response['error']['details'] = $details;
        }
        
        $this->json($response, $status);
    }

    public function paginate(array $items, int $total, int $page, int $perPage): void
    {
        $this->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
                'has_more' => ($page * $perPage) < $total
            ]
        ]);
    }

    public function redirect(string $url, int $status = 302): void
    {
        $this->status($status);
        $this->header('Location', $url);
        $this->send();
    }

    private function send(string $content = ''): void
    {
        http_response_code($this->statusCode);
        
        foreach ($this->headers as $key => $value) {
            header("{$key}: {$value}");
        }
        
        echo $content;
        exit;
    }
}

/**
 * Helper functions for quick responses
 */
function response(): Response
{
    return new Response();
}

function request(): Request
{
    static $request = null;
    if ($request === null) {
        $request = new Request();
    }
    return $request;
}
