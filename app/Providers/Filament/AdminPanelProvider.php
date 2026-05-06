<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Coach;
use App\Filament\Resources\Users\UserResource;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('/')
            ->login()
            ->passwordReset()
            ->topNavigation()
            ->brandName(new HtmlString(
                '<span class="coach-brand">Coach<span class="coach-brand-dot">.</span></span>'
            ))
            ->colors([
                // Match the brand: Coach.'s dot is orange (#ea580c). Using
                // it as the primary keeps the login CTA, save buttons and
                // any other primary action consistent with the wordmark.
                'primary' => Color::Orange,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([])
            ->homeUrl(fn (): string => Coach::getUrl())
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->userMenuItems([
                MenuItem::make()
                    ->label(fn (): string => __('users.menu.invite'))
                    ->icon('heroicon-o-user-plus')
                    ->url(fn (): string => UserResource::getUrl('create'))
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
                MenuItem::make()
                    ->label(fn (): string => __('users.menu.manage'))
                    ->icon('heroicon-o-users')
                    ->url(fn (): string => UserResource::getUrl('index'))
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_START,
                function (): string {
                    // Bust Cloudflare/browser cache when coach.css changes — appended
                    // filemtime() means the URL is unique per deploy.
                    $cssPath = public_path('css/coach.css');
                    $cssVersion = file_exists($cssPath) ? filemtime($cssPath) : '';

                    return <<<HTML
                        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
                        <meta name="theme-color" content="#0a0a0a" media="(prefers-color-scheme: dark)">
                        <meta name="theme-color" content="#fafaf7" media="(prefers-color-scheme: light)">
                        <meta name="apple-mobile-web-app-capable" content="yes">
                        <meta name="mobile-web-app-capable" content="yes">
                        <meta name="apple-mobile-web-app-status-bar-style" content="default">
                        <meta name="apple-mobile-web-app-title" content="Coach.">
                        <link rel="manifest" href="/manifest.webmanifest">
                        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
                        <link rel="preload" href="/fonts/filament/filament/inter/inter-latin-wght-normal-NRMW37G5.woff2" as="font" type="font/woff2" crossorigin>
                        <style>
                            .coach-brand{font-weight:700;font-size:1.05rem;letter-spacing:-.01em;color:#171717}
                            .coach-brand-dot{color:#ea580c;font-weight:700;margin-left:1px}
                            .fi-simple-page .coach-brand{font-size:2.5rem;letter-spacing:-.035em;line-height:1}
                            .fi-simple-page .coach-brand-dot{font-size:2.5rem}
                            .dark .coach-brand{color:#fafafa}
                            .dark .coach-brand-dot{color:#fb923c}
                        </style>
                        <link rel="stylesheet" href="/css/coach.css?v={$cssVersion}">
                        <script>
                            if ('serviceWorker' in navigator) {
                                window.addEventListener('load', () => {
                                    navigator.serviceWorker.register('/sw.js', { scope: '/' });
                                });
                            }
                        </script>
                        HTML;
                },
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
