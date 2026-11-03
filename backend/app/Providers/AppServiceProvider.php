<?php

namespace App\Providers;

use App\Support\AccessMatrix;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Hak akses per role dimuat sekali per request (scoped: dibuang setiap request baru)
        $this->app->scoped(AccessMatrix::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
