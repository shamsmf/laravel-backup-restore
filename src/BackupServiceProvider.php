<?php

namespace Shams\Backup;

use Illuminate\Support\ServiceProvider;
use Shams\Backup\Commands\Backup\Make;
use Shams\Backup\Commands\Backup\Restore;

class BackupServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/backup.php' => config_path('backup.php'),
        ]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/backup.php', 'backup');

        $this->commands([
            Make::class,
            Restore::class
        ]);
    }
}
