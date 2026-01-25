<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\ArticleTransformationService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
         $this->app->singleton(ArticleTransformationService::class, function ($app) {
            return new ArticleTransformationService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
