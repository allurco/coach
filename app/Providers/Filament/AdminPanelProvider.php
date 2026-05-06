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
                'primary' => Color::Amber,
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
