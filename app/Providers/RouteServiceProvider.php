<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{

    public function boot(): void
    {
        $this->routes(function () {
            Route::prefix('api')               // ← ✅ これが必要
                ->middleware('api')            // ← ✅ CSRFなし
                ->group(base_path('routes/api.php'));

            Route::middleware('web')           // ← ✅ CSRFあり
                ->group(base_path('routes/web.php'));
        });
    }
}
