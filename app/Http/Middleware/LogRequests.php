<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        Log::info('HTTP Request', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $request->headers->all(),
            'body' => $request->except(['password', 'password_confirmation'])
        ]);

        $response = $next($request);

        Log::info('HTTP Response', [
            'status_code' => $response->getStatusCode(),
            'response_size' => strlen($response->getContent())
        ]);

        return $response;
    }
}