<?php

declare(strict_types=1);

namespace Framework;

class Router
{
  private array $routes = [];
  private array $middlewares = [];
  private array $errorHandler;

  public function addRoute(string $method, string $path, array $controller)
  {
    $updatedPath = $this->normalizePath($path);

    $regexPath = preg_replace('#{[^/]+}#', '([^/]+)', $updatedPath);

    $this->routes[] = [
      'path' => $updatedPath,
      'method' => strtoupper($method),
      'controller' => $controller,
      'middlewares' => [],
      'regexPath' => $regexPath
    ];
  }

  private function normalizePath(string $path): string
  {
    $pathTrim = trim($path, '/');
    $pathSlash = "/{$pathTrim}/";
    $pathReplace = preg_replace('#[/]{2,}#', '/', $pathSlash);
    return $pathReplace;
  }

  public function dispatch(string $path, string $method, Container $container = null)
  {
    $updatedPath = $this->normalizePath($path);
    $updatedMethod = strtoupper($_POST['_METHOD'] ?? $method);

    foreach ($this->routes as $route) {

      if (!preg_match("#^{$route['regexPath']}$#", $updatedPath, $paramValues) || $route['method'] !== $updatedMethod) {
        continue;
      }

      array_shift($paramValues);

      preg_match_all('#{([^/]+)}#', $route['path'], $paramsKeys);

      $paramsKeys = $paramsKeys[1];

      $params = array_combine($paramsKeys, $paramValues);

      [$class, $function] = $route['controller'];

      $controllerInstance = $container ? $container->resolve($class) : new $class;

      $action = fn () => $controllerInstance->{$function}($params);

      $allMiddleware = [...$route['middlewares'], ...$this->middlewares];

      foreach ($allMiddleware as $middleware) {
        $middlewareInstance = $container ? $container->resolve($middleware) : new $middleware;
        $action = fn () => $middlewareInstance->process($action);
      }

      $action();

      return;
    }

    $this->dispatchNotFound($container);
  }

  public function addMiddleware(string $middleware)
  {
    $this->middlewares[] = $middleware;
  }

  public function addRouteMiddleware(string $middleware)
  {
    $lastRouteKey = array_key_last($this->routes);
    $this->routes[$lastRouteKey]['middlewares'][] = $middleware;
  }

  public function setErrorHandler(array $controller)
  {
    $this->errorHandler = $controller;
  }

  public function dispatchNotFound(?Container $container)
  {
    [$class, $function] = $this->errorHandler;

    $controllerInstance = $container ? $container->resolve($class) : new $class;

    $action = fn () => $controllerInstance->$function();

    foreach ($this->middlewares as $middleware) {
      $middlewareInstance = $container ? $container->resolve($middleware) : new $class;
      $action = fn () => $middlewareInstance->process($action);
    }

    $action();
  }
}
