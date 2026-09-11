<?php

namespace Encore\Admin\Middleware;

use Closure;
use Encore\Admin\Facades\Admin;
use Illuminate\Http\Request;

class Bootstrap
{
    public function handle(Request $request, Closure $next)
    {

        // Fill in translation keys that the app's published lang files
        // predate (they are install-time copies, never refreshed by package
        // updates).
        \Encore\Admin\Admin::mergeLangFallback();

        Admin::bootstrap();

        return $next($request);
    }
}
