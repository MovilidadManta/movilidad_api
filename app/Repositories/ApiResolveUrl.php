<?php

namespace App\Repositories;

use App\Interfaces\ResolveUrlInterface;
use Illuminate\Support\Facades\Http;

class ApiResolveUrl implements ResolveUrlInterface
{
    public function callService(string $url, array $listHeaders, array $data, string $format_send, int $verb_send){

        $urlSend = $this->reemplaceValuesFromJSONToData($data, $url);
        $jsonSend = $this->reemplaceValuesFromJSONToData($data, $format_send);

        // Enviar la solicitud POST al servicio SOAP sin el SOAPAction

        switch($verb_send){
            case 1:
                $response = Http::withHeaders($listHeaders)->get($urlSend);
                $body = json_decode($jsonSend, true);
                $sendResponse[] = [
                    'url' => $urlSend,
                    'body' => $body
                ];
                break;
            case 2:
                
                $body = json_decode($jsonSend, true);
                $response = Http::withHeaders($listHeaders)->post($urlSend, $body);
                $sendResponse[] = [
                    'url' => $urlSend,
                    'body' => $body
                ];
                break;
            case 3:
                $body = json_decode($jsonSend, true);
                $response = Http::withHeaders($listHeaders)->put($urlSend, $body);
                $sendResponse[] = [
                    'url' => $urlSend,
                    'body' => $body
                ];
                break;
            case 4:
                $body = json_decode($jsonSend, true);
                $response = Http::withHeaders($listHeaders)->delete($urlSend);
                $sendResponse[] = [
                    'url' => $urlSend,
                    'body' => ''
                ];
                break;
            default:
                return ['code' => -1, 'response' => 'Verbo no configurado'];
                break;
        }

        // Obtener el código de estado de la respuesta
        $statusCode = $response->getStatusCode();

        // Obtener la respuesta
        $responseJson = $response->body();

        return [
            'code' => $statusCode,
            'response' => $responseJson,
            'body_send' => json_encode($sendResponse)
        ];
    }

    public function resolveResponse(string $response, string $format_api) {
        // Si el formato es exactamente {{}} (sin clave dentro), devolver la respuesta decodificada tal cual
        if (trim($format_api) === '{{}}') {
            return json_decode($response, true);
        }

        // Paso 1: Decodificar el JSON de la respuesta a un array asociativo
        $responseArray = json_decode($response, true);
    
        // Paso 2: Buscar todas las variables dentro de {{}} en el formato API usando expresiones regulares
        preg_match_all('/\{\{(.*?)\}\}/', $format_api, $matches);
    
        // Extraer las claves encontradas dentro de las llaves {{ }}
        $variables = $matches[1];
    
        // Paso 3: Reemplazar cada clave en el formato API con el valor correspondiente del array de la respuesta
        foreach ($variables as $variable) {
            // Si la clave existe en la respuesta, reemplazarla
            if (isset($responseArray[$variable])) {
                // Reemplazar en el formato
                $format_api = str_replace('{{' . $variable . '}}', json_encode($responseArray[$variable]), $format_api);
            } else {
                // Si no existe, reemplazar con un valor vacío o lanzar un error según la lógica de negocio
                $format_api = str_replace('{{' . $variable . '}}', '""', $format_api);
            }
        }

        $format_api = json_decode($format_api, true);
    
        return $format_api;
    }    

    private function reemplaceValuesFromJSONToData(array $data, string $format_send): string
    {
        preg_match_all('/\{\{\s*(.*?)\s*\}\}/', $format_send, $matches);

        foreach ($matches[1] as $keyPath) {
            $value = $this->getValueByPath($data, $keyPath);

            // Si encontramos un valor, lo reemplazamos
            if ($value !== null && !is_array($value)) {
                $format_send = str_replace('{{' . $keyPath . '}}', $value, $format_send);
            }
        }

        return $format_send;
    }

    private function getValueByPath(array $data, string $path)
    {
        $parts = explode('.', $path);

        foreach ($parts as $part) {
            if (is_array($data)) {
                // Si es lista de arrays (ej. "examenes"), tomamos el primero
                if (array_keys($data) === range(0, count($data) - 1)) {
                    $data = $data[0] ?? null;
                }

                if (isset($data[$part])) {
                    $data = $data[$part];
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }

        return $data;
    }

    public function verifyValues($json, $response) {
        return true;
    }
}