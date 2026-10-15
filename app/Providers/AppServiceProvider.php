<?php

namespace App\Providers;

use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        $this->configureDefaults();
        $this->recordSignIns();
    }

    /**
     * Every sign-in, sign-out and failed sign-in goes to the audit trail. Passwords are never recorded.
     */
    protected function recordSignIns(): void
    {
        Event::listen(Login::class, fn (Login $event) => Audit::record('auth.login', null, [], $event->user->email ?? null, $event->user));

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user) {
                Audit::record('auth.logout', null, [], $event->user->email ?? null, $event->user);
            }
        });

        Event::listen(Failed::class, fn (Failed $event) => Audit::record('auth.failed', null, [
            'email' => $event->credentials['email'] ?? null,
        ], $event->credentials['email'] ?? null));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
