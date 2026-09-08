<?php

use App\Http\Middleware\EnsureAccountOpen;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsurePlatformOperator;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\OnlyIfEnabled;
use App\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'ensure.super.admin' => EnsureSuperAdmin::class,
            'ensure.platform.operator' => EnsurePlatformOperator::class,
            'verify.webhook' => VerifyWebhookSignature::class,
            'only-if' => OnlyIfEnabled::class,
            'ensure.account.open' => EnsureAccountOpen::class,
            'permission' => EnsurePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
