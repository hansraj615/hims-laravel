<?php

use App\Http\Middleware\AssignRequestId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->api(append: [
            AssignRequestId::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return \App\Support\ApiResponse::error(
                request: $request,
                message: 'Unauthenticated.',
                status: 401,
            );
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return \App\Support\ApiResponse::error(
                request: $request,
                message: $exception->getMessage() ?: 'This action is unauthorized.',
                status: 403,
            );
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return \App\Support\ApiResponse::error(
                request: $request,
                message: 'The given data was invalid.',
                errors: $exception->errors(),
                status: 422,
            );
        });

        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : 500;

            $message = $status === 500 && ! config('app.debug')
                ? 'Server error'
                : ($exception->getMessage() ?: 'Server error');

            return \App\Support\ApiResponse::error(
                request: $request,
                message: $message,
                status: $status,
            );
        });
    })->create();
