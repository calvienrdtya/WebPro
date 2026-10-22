<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/** Data permintaan (URL, metode, IP, user) ikut tertulis di setiap entri log, agar terlihat di menu Log Sistem. */
class AddLogContext
{
    public function handle(Request $request, Closure $next): Response
    {
        Context::add([
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
        ]);

        return $next($request);
    }
}
