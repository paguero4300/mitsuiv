<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Joaopaulolndev\FilamentEditProfile\FilamentEditProfilePlugin;
use Solutionforest\FilamentLoginScreen\Filament\Pages\Auth\Themes\Theme1\LoginScreenPage as LoginScreenPage;
//use SolutionForest\FilamentSimpleLightbox\SimpleLightboxPlugin;
use SolutionForest\FilamentSimpleLightBox\SimpleLightBoxPlugin;
use Agencetwogether\HooksHelper\HooksHelperPlugin;
use Filament\View\PanelsRenderHook;
use Hydrat\TableLayoutToggle\TableLayoutTogglePlugin;
use Hydrat\TableLayoutToggle\Persisters;
use App\Filament\Resources\ResellerAuctionResource;
use Leandrocfe\FilamentApexCharts\FilamentApexChartsPlugin;
use App\Filament\Widgets\TotalAuctionsOverview;
use App\Filament\Widgets\FinishedAuctionsOverview;
use App\Filament\Widgets\ActiveAuctionsOverview;
use App\Filament\Widgets\FailedAuctionsOverview;
use App\Filament\Widgets\ClosingPercentageOverview;
use App\Filament\Widgets\TotalSalesOverview;
use App\Filament\Widgets\AverageSalesOverview;
use App\Filament\Widgets\AuctionsStatsOverview;
use App\Filament\Widgets\AuctionsPerformanceChart;
use App\Filament\Widgets\AveragePerformanceChart;
use App\Filament\Widgets\SalesPerformanceChart;
use App\Filament\Widgets\UnifiedKpiWidget;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(LoginScreenPage::class)
            ->darkMode(false)
            ->sidebarFullyCollapsibleOnDesktop()
            ->brandName('Mitsui - Subastas')
            ->brandLogo(asset('images/logoMitsui.svg'))
            ->colors([
                'primary' => '#0075BF'
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->widgets([
                
                Widgets\AccountWidget::class,
                AuctionsStatsOverview::class,
                AuctionsPerformanceChart::class,
                SalesPerformanceChart::class,
                AveragePerformanceChart::class,
                UnifiedKpiWidget::class,
                ])
            ->plugins([
                \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make(),
                \TomatoPHP\FilamentUsers\FilamentUsersPlugin::make(),
                FilamentEditProfilePlugin::make()
                    ->slug('mi-perfil')
                    ->setTitle('Mi Perfil')
                    ->setNavigationLabel('Mi Perfil')
                    ->setNavigationGroup('Gestion')
                    ->shouldShowDeleteAccountForm(false)
                    ->shouldShowEditProfileForm(false)
                    ->shouldShowEditPasswordForm(false)
                    ->setIcon('heroicon-o-user'),
                SimpleLightBoxPlugin::make(),
                FilamentApexChartsPlugin::make(),
                TableLayoutTogglePlugin::make()
                    ->setDefaultLayout('table')
                    ->shareLayoutBetweenPages(false)
                    ->displayToggleAction()
                    ->toggleActionHook('tables::toolbar.search.after')
                    ->listLayoutButtonIcon('heroicon-o-list-bullet')
                    ->gridLayoutButtonIcon('heroicon-o-squares-2x2') // default layout for user seeing the table for the first time

                //HooksHelperPlugin::make(),

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
            ])
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn() => auth()->check() ? view('customFooter') : '',
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn() => auth()->check() ? view('customHeader') : '',
            );
    }
}
