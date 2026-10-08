<?php

declare(strict_types=1);

namespace Sereny\PostgREST;

use Illuminate\Database\DatabaseManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PostgrestServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package.
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('sereny-postgrest')
            ->hasConfigFile('postgrest');
    }

    /**
     * Register the "postgrest" database driver.
     */
    public function packageBooted(): void
    {
        $this->app->resolving('db', function (DatabaseManager $db): void {
            $this->registerPostgrestDriver($db);
        });

        if ($this->app->resolved('db')) {
            $this->registerPostgrestDriver($this->app->make('db'));
        }
    }

    /**
     * Register the "postgrest" driver on the database manager.
     */
    protected function registerPostgrestDriver(DatabaseManager $db): void
    {
        $db->extend('postgrest', function (array $config, string $name): Connection {
            $config['name'] = $name;

            return new Connection($config);
        });
    }
}
