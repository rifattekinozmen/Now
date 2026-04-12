<?php

use App\Http\Middleware\EnsureLogisticsAccess;
use App\Http\Middleware\EnsurePersonnelAccess;
use App\Http\Middleware\SetLocaleFromSession;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('logistics:refresh-tcmb-rates')
            ->dailyAt('09:10')
            ->withoutOverlapping(30);

        $schedule->command('logistics:scan-document-expiry')
            ->dailyAt('08:05')
            ->withoutOverlapping(15);

        $schedule->command('logistics:send-payment-due-reminders', ['--days=7'])
            ->dailyAt('08:30')
            ->withoutOverlapping(15);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocaleFromSession::class,
        ]);

        $middleware->alias([
            'logistics.access' => EnsureLogisticsAccess::class,
            'personnel.access' => EnsurePersonnelAccess::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
