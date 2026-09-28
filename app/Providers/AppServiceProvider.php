<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use App\Services\Distribution\MaxFlowMembersDistributor;
use App\Services\Distribution\MembersDistributor;
use Illuminate\Support\ServiceProvider;
//use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(MembersDistributor::class, MaxFlowMembersDistributor::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //URL::forceScheme('https');
        Paginator::useBootstrap();

    }
}
