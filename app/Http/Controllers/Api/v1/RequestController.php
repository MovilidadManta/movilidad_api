<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Interfaces\UserRepositoryInterface;
use App\Interfaces\OrquestadorRepositoryInterface;
use App\Interfaces\PostconversionPeticionesInterface;
use App\Interfaces\PrevalidacionPeticionesInterface;
use App\Services\ErrorHandlingService;
use App\Repositories\SoapResolveUrl;
use App\Repositories\ApiResolveUrl;

class RequestController extends Controller
{
    protected $orquestadorRepository;
    protected $postconversionRepository;
    protected $prevalidacionRepository;
    protected $userRepository;

    public function __construct(
        UserRepositoryInterface $userRepository,
        OrquestadorRepositoryInterface $orquestadorRepository, 
        PostconversionPeticionesInterface $postconversionRepository,
        PrevalidacionPeticionesInterface $prevalidacionRepository
        )
    {
        $this->orquestadorRepository = $orquestadorRepository;
        $this->postconversionRepository = $postconversionRepository;
        $this->prevalidacionRepository = $prevalidacionRepository;
        $this->userRepository = $userRepository;
    }

    public function resolveRequest(){
        try{
            // Obtener la IP del usuario
            $ip = request()->header('X-Forwarded-For') ?? request()->ip();
            // Si hay múltiples IPs (en caso de múltiples proxies), toma la primera:
            if ($ip) {
                $ip = explode(',', $ip)[0];
            }
            $idUser = request()->attributes->get('id_user');
            $usuario = $this->userRepository->ModelUserById($idUser);

            if($usuario->u_control_ips){
                $ip_found = $this->orquestadorRepository->getIps($idUser, $ip);
                if(count($ip_found) == 0){
                    return jsonResponse([], 401, 'IP no permitida');
                }
            }

            // Validar que los campos username y password existan en la solicitud
            $request = request()->validate([
                'modulo' => 'required|string',
                'peticion' => 'required|string',
                'data' => 'present|array'
            ], [
                'modulo.required' => 'El campo módulo es requerido.',
                'modulo.string' => 'El campo módulo debe ser una cadena de texto.',
                'peticion.required' => 'El campo petición es requerido.',
                'peticion.string' => 'El campo petición debe ser una cadena de texto.',
                'data.present' => 'El campo data debe estar presente, incluso si está vacío.',
                'data.array' => 'El campo data debe ser un objeto JSON válido.'
            ]);

            request()->attributes->set('requestApi', $request);

            $confList = $this->orquestadorRepository->getConfPeticionByModuloAndPeticion($request['modulo'], $request['peticion']);

            if(count($confList) == 0){
                return jsonResponse([], 404, 'Petición no encontrada');
            }

            $confList = $confList[0];
            request()->attributes->set('confList', $confList);
            
            if($usuario->u_control_peticiones){
                $peticion_found = $this->orquestadorRepository->getControlPeticiones($idUser, $confList->p_id);
                if(count($peticion_found) == 0){
                    return jsonResponse([], 404, 'Petición no encontrada');
                }
            }
            
            // Extraer los valores entre {{}} de ps_url y p_request
            $placeholdersUrl = $this->extractPlaceholders($confList->ps_url);
            $placeholdersRequest = $this->extractPlaceholders($confList->p_request_api);

            // Combinar los placeholders en un único array
            $placeholders = array_merge($placeholdersUrl, $placeholdersRequest);

            // Verificar si todos los placeholders están presentes en $request['data']
            foreach ($placeholders as $placeholder) {
                if (!$this->keyExistsDeepExtended($request['data'], $placeholder)) {
                    return jsonResponse([], 422, "Falta el campo '$placeholder' en los datos de la petición.");
                }
            }

            $itemsPreValidacion = $this->orquestadorRepository->getPrevalidacion($confList->p_id);
            //return jsonResponse($request['data']);
            $prevalidaciones = $this->prevalidacionRepository->resolve($request['data'], $itemsPreValidacion);

            if(count($prevalidaciones) > 0){
                $list_prevalidaciones = "";
                foreach ($prevalidaciones as $prevalidacion) {
                    $list_prevalidaciones .= "$prevalidacion ";
                }
                return jsonResponse([], 422, $list_prevalidaciones);
            }

            $response = [];

            switch($confList->ps_format_api){
                case 1:
                    $resolveApi = new SoapResolveUrl();
                    $response = $resolveApi->callService(
                        $confList->ps_url,
                        json_decode($confList->ps_headers_api, true),
                        $request['data'],
                        $confList->p_request_api,
                        $confList->p_verb_send
                );
                    break;
                case 2:
                    $resolveApi = new ApiResolveUrl();
                    $response = $resolveApi->callService(
                        $confList->ps_url,
                        json_decode($confList->ps_headers_api, true),
                        $request['data'],
                        $confList->p_request_api,
                        $confList->p_verb_send
                    );
                        break;
                default:
                    return jsonResponse([], 500, 'Formato de api no configurado');
                    break;
            }

            request()->attributes->set('response', $response);

            if($response['code'] == -1){
                return jsonResponse([], 500, 'Código de respuesta no configurado');
            }

            $confResponseList = $this->orquestadorRepository->getConfPeticionResponseByPIdAndCode($confList->p_id, $response['code']);

            $confResponseListValue = null;

            foreach ($confResponseList as $i => $confResponse) {
                if($resolveApi->verifyValues($confResponse->r_format_api, $response['response'])){
                    $confResponseListValue = $confResponse;
                    break;
                }
            }

            if(!$confResponseListValue){
                return jsonResponse([], 500, 'Respuesta no configurada');
            }

            $resolveResponse = $resolveApi->resolveResponse($response['response'], $confResponseListValue->r_format_api);
            $itemsConversion = $this->orquestadorRepository->getPostcoversion($confList->p_id);
            $resolveResponse = $this->postconversionRepository->resolve($resolveResponse, $itemsConversion);

            return jsonResponse($resolveResponse, $confResponseListValue->r_codigo_response);

        } catch (\Exception $e) {
            request()->attributes->set('error', $e->getMessage());
            return ErrorHandlingService::handle($e);
        }
    }

    private function extractPlaceholders($string) {
        $placeholders = [];

        // Buscar todos los {{campo}} incluyendo puntos
        preg_match_all('/{{\s*([\w\.]+)\s*}}/', json_encode($string), $matches);

        foreach ($matches[1] as $match) {
            $placeholders[] = $match;
        }

        return $placeholders;
    }

    function keyExistsDeepExtended(array $data, string $keyPath): bool {
        $parts = explode('.', $keyPath);

        $key = array_shift($parts);

        if (!array_key_exists($key, $data)) {
            return false;
        }

        $value = $data[$key];

        // Si es un array con múltiples ítems (tipo lista), y quedan más niveles, validar cada item
        if (is_array($value) && array_keys($value) === range(0, count($value) - 1) && !empty($parts)) {
            foreach ($value as $item) {
                if (!$this->keyExistsDeepExtended($item, implode('.', $parts))) {
                    return false;
                }
            }
            return true;
        }

        // Si quedan más partes, continuar recursivamente
        if (!empty($parts)) {
            return is_array($value) && $this->keyExistsDeepExtended($value, implode('.', $parts));
        }

        return true;
    }

}
