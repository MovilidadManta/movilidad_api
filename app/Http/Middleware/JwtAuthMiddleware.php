<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Http\Request;
use Carbon\Carbon;

class JwtAuthMiddleware
{
    /**
     * Maneja la solicitud entrante.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Intenta obtener el token de la cabecera de autorización
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Token no proporcionado'], 401);
        }

        try {
            // Intenta verificar el token
            $user = JWTAuth::setToken($token)->authenticate();

            if (!$user) {
                return response()->json(['error' => 'Token no válido'], 401);
            }

            // Pasa el valor del 'sub' a la variable id_user en la solicitud
            $request->attributes->set('id_user', $user->getJWTIdentifier());

        } catch (JWTException $e) {
            return response()->json(['error' => 'Token expirado o inválido'], 401);
        }

        return $next($request);
    }
}