<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;

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
        if (app()->environment('production') && config('app.debug')) {
            throw new \RuntimeException('APP_DEBUG must be false in production.');
        }

        // On shared hosting behind cPanel's SSL termination, PHP does not
        // always see the incoming request as HTTPS even though the site is
        // genuinely served over HTTPS — asset()/url()/route() then silently
        // generate http:// links, which browsers block as mixed content on
        // an https:// page. The asset FILE loads fine if you visit its URL
        // directly (you're following/typing an https link yourself) — only
        // the page's own auto-generated <link>/<script> references break,
        // which is exactly what an unstyled-but-CSS-loads-directly page
        // means. Forcing the scheme here whenever APP_URL is https removes
        // the dependency on PHP correctly detecting the real scheme at all.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        Paginator::useBootstrapFive();
        View::composer('*', function ($view) {
            try {
                $view->with('companyName', Setting::value('company_name', 'Ivory Gifts'));
                $logoPath = Setting::value('logo_path');
                $view->with('companyLogoUrl', $logoPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($logoPath) : null);
            } catch (\Throwable) {
                $view->with('companyName', 'Ivory Gifts');
                $view->with('companyLogoUrl', null);
            }
        });

        // Footer financial bar (Bank/Cash/Inventory/Payables/Receivables)
        // — scoped to just the authenticated layout, which renders once
        // per page load, rather than the '*' composer above which would
        // run these real ledger queries on every single view render.
        View::composer('layouts.app', function ($view) {
            try {
                $view->with('financialSummary', app(\App\Services\FinancialSummaryService::class)->summary());
            } catch (\Throwable) {
                $view->with('financialSummary', null);
            }
            try {
                $view->with('notifications', app(\App\Services\NotificationService::class)->all());
            } catch (\Throwable) {
                $view->with('notifications', []);
            }
        });
    }
}
