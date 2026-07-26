<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

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
        // Build the password-reset link that goes into the email.
        // IMPORTANT: this path MUST match the React route in App.jsx
        // (`/reset-password`), otherwise the emailed link 404s.
        // Falls back to app.url if FRONTEND_URL isn't configured, so the
        // link is never domain-less.
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $frontend = rtrim(config('app.frontend_url', config('app.url')), '/');
 
            return $frontend . '/reset-password?token=' . $token
                . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
        });
    }
}
