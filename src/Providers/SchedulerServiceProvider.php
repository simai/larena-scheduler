<?php

declare(strict_types=1);

namespace Larena\Scheduler\Providers;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\ServiceProvider;
use Larena\Access\Runtime\AccessOperationRegistry;
use Larena\Scheduler\Audit\SchedulerAuditRecorder;
use Larena\Scheduler\Commands\SchedulerTickCommand;
use Larena\Scheduler\Contracts\SchedulerClock;
use Larena\Scheduler\Persistence\DatabaseScheduleStore;
use Larena\Scheduler\Runtime\ScheduledOperationRegistry;
use Larena\Scheduler\Runtime\SchedulerAccessOperationRegistrar;
use Larena\Scheduler\Runtime\ScheduleRecurrenceCalculator;
use Larena\Scheduler\Runtime\SystemSchedulerClock;
use Larena\Scheduler\Services\ScheduleOperations;
use Larena\Scheduler\Services\SchedulerTickService;

final class SchedulerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/larena-scheduler.php', 'larena-scheduler');
        $this->app->singleton(SchedulerClock::class, SystemSchedulerClock::class);
        $this->app->singleton(ScheduleRecurrenceCalculator::class);
        $this->app->singleton(ScheduledOperationRegistry::class);
        $this->app->singleton(SchedulerAccessOperationRegistrar::class);
        $this->app->scoped(SchedulerAuditRecorder::class);
        $this->app->scoped(
            DatabaseScheduleStore::class,
            static fn (Application $app): DatabaseScheduleStore => new DatabaseScheduleStore(
                $app->make(ConnectionInterface::class),
                $app->make(ScheduleRecurrenceCalculator::class),
            ),
        );
        $this->app->scoped(ScheduleOperations::class);
        $this->app->scoped(
            SchedulerTickService::class,
            static fn (Application $app): SchedulerTickService => new SchedulerTickService(
                $app->make(DatabaseScheduleStore::class),
                $app->make(ScheduledOperationRegistry::class),
                $app->make(ScheduleRecurrenceCalculator::class),
                $app->make(SchedulerClock::class),
                $app->make(SchedulerAuditRecorder::class),
                (int) $app->make('config')->get('larena-scheduler.tick.max_schedules', 100),
                (int) $app->make('config')->get('larena-scheduler.tick.missed_grace_seconds', 60),
            ),
        );
    }

    public function boot(AccessOperationRegistry $access, Config $config): void
    {
        $this->app->make(SchedulerAccessOperationRegistrar::class)->register($access);
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'larena-scheduler');
        if ($this->app->runningInConsole()) {
            $this->commands([SchedulerTickCommand::class]);
        }
        if ((bool) $config->get('larena-scheduler.admin_routes.enabled', true)) {
            $this->app->make('router')
                ->middleware((array) $config->get('larena-scheduler.admin_routes.middleware'))
                ->prefix((string) $config->get('larena-scheduler.admin_routes.prefix', 'admin/scheduler'))
                ->group(__DIR__.'/../../routes/admin.php');
        }
    }
}
