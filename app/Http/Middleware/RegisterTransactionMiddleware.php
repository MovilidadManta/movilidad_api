<?php

namespace App\Http\Middleware;

use Closure;
use App\Interfaces\OrquestadorRepositoryInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Helpers\GuidHelper;
use App\Services\GeoIPService;
use Illuminate\Support\Facades\Log;

class RegisterTransactionMiddleware
{
    protected $orquestadorRepository;

    public function __construct(OrquestadorRepositoryInterface $orquestadorRepository)
    {
        $this->orquestadorRepository = $orquestadorRepository;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->boolean('nl')) {
            return $next($request);
        }

        $response = $next($request);

        $guid = GuidHelper::GUIDv4();
        $ip = $request->header('X-Forwarded-For') ?? $request->ip();
        $first_ip = $ip;
        $idUser = $request->attributes->get('id_user');

        // Asegúrate de que puedes acceder a la data correcta desde el request y la respuesta
        $confList = $request->attributes->get('confList');
        $responseApi = $request->attributes->get('response');
        $requestApi = $request->attributes->get('requestApi');
        $error = $request->attributes->get('error');

        try {
            $geoIPService = new GeoIPService();
            if ($ip) {
                $first_ip = explode(',', $ip)[0];
            }
            $geoData = $geoIPService->getGeoData($first_ip);

            $auditData = [
                'ip' => $first_ip,
                'server_ip' => $request->server('SERVER_ADDR') ?? null,
                'client_port' => $request->server('REMOTE_PORT') ?? null,
                'referer' => $request->header('Referer') ?? null,
                'protocol' => $request->server('SERVER_PROTOCOL') ?? null,
                'host' => $request->header('Host') ?? null,
                'proxy_ips' => $request->server('HTTP_X_FORWARDED_FOR') ?? null,
                'language_cliente' => $request->header('Accept-Language') ?? null,
                'user_agent' => $request->userAgent(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'latitude' => $geoData['latitude'] ?? null,
                'longitude' => $geoData['longitude'] ?? null,
                'iso_code' => $geoData['iso_code'] ?? null,
                'country' => $geoData['country'] ?? null,
                'region' => $geoData['region'] ?? null,
                'city' => $geoData['city'] ?? null,
                'postal_code' => $geoData['postal_code'] ?? null,
                'network' => $geoData['network'] ?? null,
                'time_zone' => $geoData['time_zone'] ?? null,
                'accuracy_radius' => $geoData['accuracy_radius'] ?? null,
                'continent' => $geoData['continent'] ?? null,
                'continent_code' => $geoData['continent_code'] ?? null,
                'asn' => $geoData['asn'] ?? null,
                'asn_organization' => $geoData['asn_organization'] ?? null,
                'user_type' => $geoData['user_type'] ?? null,
                'is_anonymous' => $geoData['is_anonymous'] ?? null,
                'is_anonymous_proxy' => $geoData['is_anonymous_proxy'] ?? null,
                'is_satellite_provider' => $geoData['is_satellite_provider'] ?? null,
            ];

            // Guardar en la base de datos o logs
            Log::info('Audit Data', $auditData);
        } catch (\Throwable $th) {
            //throw $th;
        }
        

        // Continuar la ejecución de la solicitud
        
        $r1 = $this->orquestadorRepository->registerTransaccion(
            $confList ? $confList->p_id : -1,
            $guid,
            $confList ? $confList->ps_format_api : -1,
            $confList ? $confList->p_verb_send : -1,
            $responseApi ? $responseApi['body_send'] : '',
            json_encode($requestApi),
            json_encode($auditData),
            $ip,
            $idUser
        );

        $r2 = $this->orquestadorRepository->registerTransaccion(
            $confList ? $confList->p_id : -1,
            $guid,
            $confList ? $confList->ps_format_api : -1,
            $responseApi ? $responseApi['code'] : -1,
            $responseApi ? 
                $responseApi['response'] 
                : $error,
            $response,
            json_encode($auditData),
            $ip,
            $idUser
        );

        // Devolver la respuesta final
        return $response;
    }
}