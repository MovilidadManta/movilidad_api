<?php 

function jsonResponse($data = [], $status = 200, $message = 'OK', $errors = [])
{
    return response()->json(compact('data', 'status', 'message', 'errors'), $status)->withHeaders([
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none';",
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'no-referrer',
        'Permissions-Policy' => 'geolocation=(), camera=()',
        'Cache-Control' => 'no-store, no-cache, must-revalidate',
        'Access-Control-Allow-Origin' => '*', // Cambiar "*" por dominios específicos si aplica
    ]);
}