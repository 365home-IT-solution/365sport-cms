<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

Route::name('filament.')
    ->group(function () {
        foreach (Filament::getPanels() as $panel) {
            $panelId = $panel->getId();
            $domains = $panel->getDomains();
            $hasTenancy = $panel->hasTenancy();
            foreach ((empty($domains) ? [null] : $domains) as $domain) {
                Route::domain($domain)
                    ->middleware($panel->getMiddleware())
                    ->name("{$panelId}.")
                    ->prefix($panel->getPath())
                    ->group(function () use ($panel, $hasTenancy) {
                        if ($panel->hasPlugin('filament-breezy')) {
                            $route = $hasTenancy ? '/{tenant}/two-factor-authentication' : '/two-factor-authentication';
                            $action = filament('filament-breezy')->getTwoFactorRouteAction();

                            // 2FA chưa bật (enableTwoFactorAuthentication() không được gọi) thì action
                            // = null — đăng ký route sẽ gây 500 "Route has no action" khi bị truy cập.
                            if ($action !== null) {
                                Route::get($route, $action)->name('auth.two-factor');
                            }
                        }
                    });
            }
        }
    });
