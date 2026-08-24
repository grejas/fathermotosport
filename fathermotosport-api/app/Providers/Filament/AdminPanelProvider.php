<?php

namespace App\Providers\Filament;

use App\Http\Middleware\EnsurePanelAccess;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('FatherMotoSport')
            ->colors([
                'primary' => Color::hex('#E8001D'),
                'danger' => Color::hex('#E8001D'),
                'warning' => Color::Amber,
                'success' => Color::Emerald,
                'gray' => Color::Neutral,
            ])
            ->darkMode(true)
            ->defaultThemeMode(ThemeMode::Dark)
            ->navigationGroups([
                NavigationGroup::make('Catálogo'),
                NavigationGroup::make('Ventas'),
                NavigationGroup::make('Clientes'),
                NavigationGroup::make('Marketing'),
                NavigationGroup::make('Blog'),
                NavigationGroup::make('Equipo'),
                NavigationGroup::make('Configuración'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsurePanelAccess::class,
            ]);

        // Logo de marca opcional si existe el archivo.
        if (file_exists(public_path('images/logo.png'))) {
            $panel->brandLogo(asset('images/logo.png'))->brandLogoHeight('2rem');
        }

        return $panel;
    }
}
