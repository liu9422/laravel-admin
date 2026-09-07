<?php

namespace Encore\Admin\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Fallback used when config('admin.security_headers') is missing - i.e.
     * existing installations whose config/admin.php predates this middleware
     * (config keys are per-installation, they are never merged with the
     * published file on upgrade). Kept in sync with the defaults published in
     * config/admin.php.
     *
     * @var array
     */
    protected $defaults = [
        'enabled'                 => true,
        'X-Content-Type-Options'  => 'nosniff',
        'Referrer-Policy'         => 'strict-origin-when-cross-origin',
        'X-Frame-Options'         => 'SAMEORIGIN',
    ];

    public function handle(Request $request, Closure $next)
    {
        $config = config('admin.security_headers', $this->defaults);

        if (!is_array($config) || empty($config['enabled'])) {
            return $next($request);
        }

        /** @var Response $response */
        $response = $next($request);

        foreach ($config as $header => $value) {
            if ($header === 'enabled' || $value === null || $value === false || $value === '') {
                continue;
            }

            $response->headers->set($header, $value);
        }

        return $response;
    }
}
