<?php

namespace Upaid\Regulations\Providers;

use Illuminate\Support\ServiceProvider;

class RegulationsServiceProvider extends ServiceProvider
{
    protected $commands = [
        'Upaid\Regulations\Commands\GetRegulationsCommand',
    ];

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->commands($this->commands);
    }
}
