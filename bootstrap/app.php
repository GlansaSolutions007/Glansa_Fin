<?php

use App\Http\Middleware\CheckJwtToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Configuration\Exceptions;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'check.jwt' => CheckJwtToken::class,
            'web.session' => \Illuminate\Session\Middleware\StartSession::class,
            'multi-auth' => App\Http\Middleware\MultiAuthGuard::class,
        ]);
         $middleware->validateCsrfTokens(except: [
            '/*' // <-- exclude this route
        ]);
        
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
