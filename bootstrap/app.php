<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponseMiddleware::class
        ]);
        $middleware->alias([
            'jwtauth' => \App\Http\Middleware\JwtAuthMiddleware::class,
            'addNewToken' => \App\Http\Middleware\AddNewTokenToResponse::class,
            'register.transaction' => \App\Http\Middleware\RegisterTransactionMiddleware::class,
          ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Manejo de excepción 404 (Not Found)
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return response()->json([
                'message' => 'Route not found.'
            ], 404);
        });

        // Manejo de excepción 405 (Method Not Allowed)
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            return response()->json([
                'message' => 'Method not allowed.'
            ], 405);
        });
    })->create();
