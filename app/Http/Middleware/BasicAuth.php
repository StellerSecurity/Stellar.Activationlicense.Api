<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredUsername = config('activation_license.api_username');
        $configuredPassword = config('activation_license.api_password');
        $providedUsername = $request->getUser();
        $providedPassword = $request->getPassword();

        $isConfigured = is_string($configuredUsername)
            && $configuredUsername !== ''
            && is_string($configuredPassword)
            && $configuredPassword !== '';

        $isAuthenticated = $isConfigured
            && is_string($providedUsername)
            && is_string($providedPassword)
            && hash_equals($configuredUsername, $providedUsername)
            && hash_equals($configuredPassword, $providedPassword);

        if (! $isAuthenticated) {
            return $this->unauthorizedResponse();
        }

        return $next($request);
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'response_code' => 401,
            'response_message' => 'Unauthorized.',
        ], 401, [
            'WWW-Authenticate' => 'Basic realm="Activation License API"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
