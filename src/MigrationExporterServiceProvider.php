<?php

namespace JugalKariya\LaravelMigrationExporter;

use Illuminate\Support\ServiceProvider;

class MigrationExporterServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->commands(MigrationExporter::class);
    }
}
