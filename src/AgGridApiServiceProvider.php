<?php

namespace Radix\Aggrid;

use Illuminate\Support\ServiceProvider;

class AgGridApiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->make('Radix\Aggrid\AgGridApiController');
        $this->app->make('Radix\Aggrid\AgGridApiService');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}
