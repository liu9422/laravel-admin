<?php

namespace Encore\Admin\Middleware;

use Closure;
use Encore\Admin\Facades\Admin;
use Illuminate\Http\Request;

class Bootstrap
{
    public function handle(Request $request, Closure $next)
    {
        // Class statics survive between requests on resident-memory runtimes
        // (Laravel Octane, FrankenPHP worker mode, `php artisan serve`);
        // start every request from a clean slate.
        \Encore\Admin\Admin::flushState();

        Admin::bootstrap();

        return $next($request);
    }
}
