<?php

declare(strict_types=1);

use App\Console\Commands\DispatchImportJobsCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command(DispatchImportJobsCommand::class)
            ->cron(config('services.import.cron'))
            ->when(fn () => config('services.import.enabled'))
            ->sentryMonitor();
    })
    ->create();
