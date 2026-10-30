<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();
    }

    protected function gate(): void
    {
        // Any authenticated backoffice admin may view Horizon.
        Gate::define('viewHorizon', function ($user = null) {
            return auth('admin')->check();
        });
    }
}
