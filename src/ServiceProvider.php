<?php


namespace Sevenshi\Eshelper;

use Sevenshi\Eshelper\Commands\EsMigrate as EsMigrateCommand;
use Sevenshi\Eshelper\Commands\SyncComands\SyncProducts as SyncProductsCommand;


class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    protected $defer = true;

    public function boot()
    {
        $this->publishes([
            __DIR__.'/commands/EsMigrate.php' => app_path('Console/Commands/EsMigrate.php'),
            __DIR__.'/Esindices/BaseIndex.php' => app_path('Esindices/BaseIndex.php'),
            __DIR__.'/Esindices/ProductsIndex.php' => app_path('Esindices/ProductsIndex.php'),
            __DIR__.'/commands/SyncComands/SyncProducts.php' => app_path('Console/Commands/SyncComands/SyncProducts.php'),
        ]);
    }


    public function register()
    {
        $this->app->singleton(Eshelper::class, function(){
            return new Eshelper(config('elasticsearch.elasticsearch.hosts'));
        });

        $this->app->alias(Eshelper::class, 'eshelper');
        $this->registerCommands();

    }

    public function provides()
    {
        return array_merge([Eshelper::class, 'eshelper'], [EsMigrateCommand::class]);
    }


    /**
     * Register lang:publish command.
     */
    protected function registerCommands()
    {
        $this->commands(EsMigrateCommand::class);

        $this->commands(SyncProductsCommand::class);
    }
}