<?php

namespace App\Providers;

use App\Imports\UsersImportProfile;
use App\Support\DemoBulkFlowActor;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DemoBulkFlowActor::class);
        $this->app['config']->set('bulkflow.profiles', [UsersImportProfile::class]);
        $this->app['config']->set('bulkflow.profile_authorize', static fn (): bool => true);
        $this->app['config']->set('bulkflow.profile_actor_fingerprint', static fn (): string => app(DemoBulkFlowActor::class)->id());
        $this->app['config']->set('bulkflow.template_actor_id', static fn (): string => app(DemoBulkFlowActor::class)->id());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
