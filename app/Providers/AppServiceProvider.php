<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // Inertia props are plain objects; no {"data": ...} wrapper.
        JsonResource::withoutWrapping();

        Password::defaults(fn (): Password => Password::min(8)->letters()->numbers());

        // The system admin passes every permission check.
        Gate::before(fn (User $user): ?bool => $user->isSystemAdmin() ? true : null);
    }
}
