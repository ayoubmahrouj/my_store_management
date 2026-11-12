<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:callable|array, middleware:array<int, callable>}> */
    private array $routes = [];

    /** @param callable|array<int, string> $handler */
    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    /** @param callable|array<int, string> $handler */
    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /** @param callable|array<int, string> $handler */
    public function add(string $method, string $path, callable|array $handler, array $middleware = []): void
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $path) ?? $path;
        $this->routes[] = [
            'method'     => strtoupper($method),
            'pattern'    => '#^' . $pattern . '$#',
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $path = $request->path();
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            foreach ($route['middleware'] as $middleware) {
                $result = $middleware($request);
                if ($result instanceof Response) {
                    return $result;
                }
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $handler = $route['handler'];

            if (is_array($handler)) {
                [$class, $method] = $handler;
                $controller = new $class();
                return $controller->{$method}($request, ...array_values($params));
            }

            return $handler($request, ...array_values($params));
        }

        if ($request->isApi()) {
            return Response::json([
                'success' => false,
                'message' => 'Ressource introuvable.',
                'errors'  => [],
            ], 404);
        }

        return View::render('errors/404', ['title' => 'Page introuvable'], 'auth')->withStatus(404);
    }
}
