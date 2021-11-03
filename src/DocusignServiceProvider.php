<?php namespace Tjphippen\Docusign;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\AliasLoader;

class DocusignServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function boot()
    {
        $configPath = __DIR__.'/config/config.php';
        $this->publishes([
            $configPath => config_path('docusign.php')
        ], "config");
        $this->mergeConfigFrom($configPath, "docusign");

        Route::group($this->routeConfiguration(), function () {
            Route::get('callback', [\Tjphippen\Docusign\DocusignController::class, 'callback']);
        });
    }

    /**
     * Get the Telescope route group configuration array.
     *
     * @return array
     */
    private function routeConfiguration()
    {
        return [
            'prefix' => 'ds',
            'middleware' => 'web',
        ];
    }

    public function register()
    {
        $this->app->singleton('docusign', function ($app)
        {
            return new Docusign($app->config->get('docusign', array()));
        });

        $this->app->booting(function()
        {
            AliasLoader::getInstance()->alias('Docusign', 'Tjphippen\Docusign\Facades\Docusign');
        });
    }

    public function provides()
    {
        return ['docusign'];
    }
}
