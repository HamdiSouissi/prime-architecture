<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Password::defaults(function () {
            $rule = Password::min((int) config('security.password_min_length'));

            return config('security.check_compromised') ? $rule->uncompromised() : $rule;
        });

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                activity('authentication')->causedBy($event->user)->event('login.succeeded')->log('login.succeeded');
            }
        });

        Event::listen(Failed::class, function (Failed $event): void {
            // Never record the supplied credentials or password.
            activity('authentication')->causedByAnonymous()->event('login.failed')->log('login.failed');
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User) {
                activity('authentication')->causedBy($event->user)->event('logout')->log('logout');
            }
        });
    }
}
