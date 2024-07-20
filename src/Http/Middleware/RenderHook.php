<?php

namespace OwainJones74\FilamentGaze\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

class RenderHook
{
    public function handle($request, $next)
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::CONTENT_START,
            fn (): string => Blade::render('<livewire:filament-gaze-banner />'),
        );

        return $next($request);
    }
}
