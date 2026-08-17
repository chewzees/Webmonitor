<?php
declare(strict_types=1);

final class Request
{
    private string $method;
    private string $path;
    /** @var array<string, string> */
    private array $params;
    /** @var array<string, mixed> */
    private array $query;
    private mixed $body;
    /** @var array<string, string> */
    private array $headers;

    /**
     * @param array<string, string> $params
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     */
    public function __construct(
        string $method,
        string $path,
        array $params = [],
        array $query = [],
        mixed $body = null,
        array $headers = [],
    ) {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->params = $params;
        $this->query = $query;
        $this->body = $body;
        $this->headers = $headers;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function param(string $key, ?string $default = null): ?string
    {
        return $this->params[$key] ?? $default;
    }

    /** @return array<string, string> */
    public function params(): array
    {
        return $this->params;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function queryAll(): array
    {
        return $this->query;
    }

    public function body(): mixed
    {
        return $this->body;
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        if (!is_array($this->body)) {
            return [];
        }
        return $this->body;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = strtolower($name);
        return $this->headers[$key] ?? $default;
    }

    public function ip(): ?string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? null;
    }

    public function userAgent(): ?string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }

    public function withParams(array $params): self
    {
        $clone = clone $this;
        $clone->params = $params;
        return $clone;
    }

    public static function fromGlobals(string $path): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($k, 5)));
                $headers[$name] = (string) $v;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        $raw = file_get_contents('php://input') ?: '';
        $body = null;
        $ct = $headers['content-type'] ?? '';
        if ($raw !== '' && str_contains($ct, 'application/json')) {
            $decoded = json_decode($raw, true);
            $body = is_array($decoded) ? $decoded : null;
        } elseif (!empty($_POST)) {
            $body = $_POST;
        }

        /** @var array<string, mixed> $query */
        $query = $_GET;

        return new self($method, $path, [], $query, $body, $headers);
    }
}
