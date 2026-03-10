<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth;
use Kreait\Firebase\Database;

class FirebaseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
         // Bind Firebase Factory as singleton
        $this->app->singleton('firebase', function ($app) {
            $factory = (new Factory)
                ->withServiceAccount(config('firebase.credentials'))
                ->withDatabaseUri(config('firebase.database.url'));

            return $factory;
        });

        // Bind Firebase Auth
        $this->app->singleton(Auth::class, function ($app) {
            return $app->make('firebase')->createAuth();
        });

        // Bind Firebase Database
        $this->app->singleton(Database::class, function ($app) {
            return $app->make('firebase')->createDatabase();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
