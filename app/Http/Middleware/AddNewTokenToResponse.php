<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;

class AddNewTokenToResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Intenta obtener el token de la cabecera de autorización
        $token = $request->bearerToken();

        // Obtener el tiempo de expiración del token
        $payload = JWTAuth::getPayload($token);
        $exp = Carbon::createFromTimestamp($payload->get('exp'));
        $remainingTime = Carbon::now()->diffInMinutes($exp, false); // false para obtener valores negativos si ya expiró

        // Si al token le queda 1 minuto o menos para expirar, generamos un nuevo token
        if ($remainingTime <= 1) {
            $newToken = JWTAuth::refresh($token);
            $request->attributes->set('new_token', $newToken);
        }

        // Procesar la solicitud
        $response = $next($request);

        // Acceder al nuevo token (si se generó uno)
        $newToken = $request->attributes->get('new_token');

        // Si hay un nuevo token, lo añadimos a la respuesta
        if ($newToken) {
            $content = json_decode($response->getContent(), true);
            $content['new_token'] = $newToken; // Añadimos el nuevo token

            // Establecer el nuevo contenido en la respuesta
            $response->setContent(json_encode($content));
        }

        return $response;
    }
}
