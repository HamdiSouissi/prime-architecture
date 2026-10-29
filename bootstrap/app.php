<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['role' => RoleMiddleware::class, 'permission' => PermissionMiddleware::class, 'role_or_permission' => RoleOrPermissionMiddleware::class]);
        $middleware->trustHosts(at: function () {
            $host = parse_url(config('app.url'), PHP_URL_HOST);

            return is_string($host) && $host !== '' ? ['^'.preg_quote($host, '/').'$'] : [];
        }, subdomains: false);
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [SetLocale::class, EnsureUserIsActive::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request): ?Response {
            if ($request->hasSession()) {
                $locale = $request->session()->get('locale');
                if (is_string($locale) && in_array($locale, config('architecture.locales'), true)) {
                    App::setLocale($locale);
                }
            }

            return null;
        });
    })->create();
