<?php

namespace Pace;

use Pace\Soap\Factory as SoapFactory;
use Illuminate\Support\ServiceProvider;

class PaceServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/pace.php' => config_path('pace.php'),
        ]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Client::class, function ($app) {
            $config = $app['config']['pace'];

            return new Client(
                new SoapFactory(),
                $config['host'],
                $config['login'],
                $config['password'],
                $config['scheme']
            );
        });
    }
}
