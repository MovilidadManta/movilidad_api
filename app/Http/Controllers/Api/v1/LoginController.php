<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Interfaces\UserRepositoryInterface;
use App\Interfaces\OrquestadorRepositoryInterface;
use App\Services\ErrorHandlingService;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Services\GeoIPService;

class LoginController extends Controller
{
    protected $userRepository;
    protected $orquestadorRepository;

    public function __construct(UserRepositoryInterface $userRepository, OrquestadorRepositoryInterface $orquestadorRepository)
    {
        $this->userRepository = $userRepository;
        $this->orquestadorRepository = $orquestadorRepository;
    }

    public function login()
    {
        $ipAll = request()->header('X-Forwarded-For') ?? request()->ip();
        $user = 0; // Inicializamos la variable de usuario

        try {
            // Obtener la IP del usuario
            $ip = $ipAll;

            if ($ipAll) {
                $ip = explode(',', $ipAll)[0];
            }

            // Validar que los campos username y password existan en la solicitud
            $credentials = request()->validate([
                'username' => 'required|string',
                'password' => 'required|string'
            ], [
                'username.required' => 'Campo :attribute es requerido.',
                'username.string' => 'Campo :attribute debe ser Alfanumérico.',
                'password.required' => 'Campo :attribute es requerido.',
                'password.string' => 'Campo :attribute debe ser Alfanumérico.',
            ]);

            $user = $this->userRepository->validateLogin($credentials['username'], $credentials['password'], $ip, $user);

            $geoIPService = new GeoIPService();

            $geoData = $geoIPService->getGeoData($ip);

            $auditData = [
                'ip' => $ipAll,
                'server_ip' => request()->server('SERVER_ADDR') ?? null,
                'client_port' => request()->server('REMOTE_PORT') ?? null,
                'referer' => request()->header('Referer') ?? null,
                'protocol' => request()->server('SERVER_PROTOCOL') ?? null,
                'host' => request()->header('Host') ?? null,
                'proxy_ips' => request()->server('HTTP_X_FORWARDED_FOR') ?? null,
                'language_cliente' => request()->header('Accept-Language') ?? null,
                'user_agent' => request()->userAgent(),
                'method' => request()->method(),
                'url' => request()->fullUrl(),
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

            if ($user > 0) {
                // Buscar al usuario en la base de datos
                $usuario = $this->userRepository->ModelUserByUsername($credentials['username']);

                // Generar el JWT si el usuario es válido
                if (!$token = JWTAuth::fromUser($usuario)) {
                    return jsonResponse([], 500, 'No se pudo generar el token', ['error' => 'No se pudo generar el token']);
                    $this->orquestadorRepository->registerLoginUser(
                        "ERROR_SERVER" ,$credentials['username'],$credentials['password'],json_encode($auditData), $ipAll, $user
                    );
                }

                $this->orquestadorRepository->registerLoginUser(
                    "SUCCESS_LOGIN" ,$credentials['username'],$credentials['password'],json_encode($auditData), $ipAll, $user
                );

                // Devolver respuesta con el token y el tiempo de expiración
                return jsonResponse([
                    'token' => $token,
                    //'expires_in' => auth('api')->factory()->getTTL() * 60
                ]);

            } else {
                // Si el procedimiento devuelve un id <= 0, credenciales incorrectas
                $this->orquestadorRepository->registerLoginUser(
                    "INCORRECT_PASSWORD" ,$credentials['username'],$credentials['password'],json_encode($auditData), $ipAll, $user
                );
                return jsonResponse([], 404, 'Usuario o contraseña incorrectos');
            }
        } catch (\Exception $e) {
            $this->orquestadorRepository->registerLoginUser(
                "EXCEPTION" ,'','',["error"=> $e->getMessage(), "body" => request()->all()], $ipAll, $user
            );
            return ErrorHandlingService::handle($e);
        }
    }

    public function verify(Request $request){

        $response = [];

        return jsonResponse($response);
    }


    public function me()
    {
        return response()->json(JWTAuth::parseToken()->authenticate());
    }
}
