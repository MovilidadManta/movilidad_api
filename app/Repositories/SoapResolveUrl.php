<?php

namespace App\Repositories;

use App\Interfaces\ResolveUrlInterface;
use GuzzleHttp\Client;

class SoapResolveUrl implements ResolveUrlInterface
{
    public function callService(string $url, array $listHeaders, array $data, string $format_send, int $verb_send){
        // Crear cliente Guzzle
        $client = new Client();

        $xmlRequest = $this->reemplaceValuesFromJSONToData($data, $format_send);

        // Enviar la solicitud POST al servicio SOAP sin el SOAPAction

        switch($verb_send){
            case 2:
                $response = $client->post($url, [
                    'headers' => $listHeaders,
                    'body' => $xmlRequest,
                ]);
                break;
            default:
                return ['code' => -1, 'response' => 'Verbo no configurado'];
                break;
        }

        // Obtener el código de estado de la respuesta
        $statusCode = $response->getStatusCode();

        // Obtener la respuesta
        $responseXml = $response->getBody()->getContents();

        return [
            'code' => $statusCode,
            'response' => $responseXml,
            'body_send' => $xmlRequest
        ];
    }

    public function resolveResponse(string $response, string $format_api) {
        $this->verifyXML($response);
    
        // Paso 1: Extraer el valor del XML de forma dinámica y limpiar namespaces
        $cleanXml = $this->removeNamespaces($response);
    
        // Cargar el XML en un objeto SimpleXMLElement
        $xml = new \SimpleXMLElement($cleanXml);
    
        // Registrar namespaces para poder acceder a los nodos con prefijos
        $namespaces = $xml->getNamespaces(true);
    
        // Convertir el XML a array para una búsqueda más sencilla
        $xmlArray = $this->xmlToArray($xml, $namespaces);
    
        // Paso 2: Buscar todas las variables con {{}} en el JSON
        preg_match_all('/\{\{(.*?)\}\}/', $format_api, $matches);
    
        // Extraer las variables que están entre llaves {{}} del JSON
        $variables = $matches[1];
    
        // Paso 3: Reemplazar esas variables en el JSON con valores correspondientes del XML
        foreach ($variables as $variable) {
            // Verificar si el campo es opcional (si termina con '?')
            $isOptional = false;
            if (substr($variable, -1) === '?') {
                $isOptional = true;
                // Remover el '?' del nombre de la variable
                $variable = rtrim($variable, '?');
            }
    
            // Separar la ruta de nodos por '.' (para acceder a subnodos)
            $path = explode('.', $variable);
    
            // Intentar obtener el valor desde el array del XML
            $value = $xmlArray;
            foreach ($path as $key) {
                if (isset($value[$key])) {
                    $value = $value[$key]; // Continuar buscando el valor en el array
                } else {
                    $value = null; // Si no se encuentra, se devuelve null
                    break;
                }
            }
    
            // Si encontramos un valor, lo procesamos
            if ($value !== null) {
                // Si el valor es un array (por ejemplo, una lista de <item>), convertirlo a JSON
                if (is_array($value)) {
                    $value = json_encode($value);
                } else {
                    // Reemplazar comillas dobles dentro del string para que sea JSON válido
                    $value = str_replace('"', '\"', $value);
                }
    
                // Reemplazar la variable con su valor en el JSON
                $values_variable = $variable;
                $values_variable .= $isOptional ? '?' : '';
                $format_api = str_replace('{{' . $values_variable  . '}}', $value, $format_api);
            } else if ($isOptional) {
                // Si es opcional y no se encuentra, reemplazar con "null"
                $values_variable = $variable . '?';
                $format_api = str_replace('{{' . $values_variable . '}}', 'null', $format_api);
            }
        }
    
        // Paso 4: Convertir el JSON resultante en un array PHP
        $format_api = $this->cleanJsonString($format_api);
        $jsonArray = json_decode($format_api, true);
    
        // Paso 5: Eliminar claves con valores "null" o null
        $jsonArray = $this->removeNullValues($jsonArray);
    
        return $jsonArray;
    }    
    
    /**
     * Función recursiva para eliminar las claves con valores null o "null" de un array
     */
    private function removeNullValues(array $array) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->removeNullValues($value); // Recursividad para subarrays
            }
    
            if ($value === null || $value === "null") {
                unset($array[$key]); // Eliminar la clave si el valor es null o "null"
            }
        }
        return $array;
    }        
	
	private function cleanJsonString($jsonString) {
		// Reemplazar los caracteres de control
		$jsonString = str_replace(["\r", "\n", "\t"], '', $jsonString);
		return $jsonString;
	}

    private function reemplaceValuesFromJSONToData(array $data, string $format_send){
        preg_match_all('/\{\{(.*?)\}\}/', $format_send, $matches);

        // Reemplazar cada variable si existe en el array 'data'
        foreach ($matches[1] as $variable) {
            $format_send = str_replace('{{' . $variable . '}}', $data[$variable], $format_send);
        }

        return $format_send;
    }

    private function verifyXML($xmlString){
        libxml_use_internal_errors(true);
        $xml = new \SimpleXMLElement($xmlString);
        if ($xml === false) {
            // Manejo de errores
            $errors = libxml_get_errors();
            // Procesar errores
            libxml_clear_errors();
            return $errors;
        }
        return $xml;
    } 


    // Convertir el SimpleXMLElement en un array para facilitar el acceso a los nodos
    private function xmlToArray($xml, $namespaces) {
        $array = [];
    
        foreach ($xml->children() as $child) {
            $name = $child->getName();
    
            // Si el nodo tiene un namespace, añadirlo al nombre del nodo
            foreach ($namespaces as $prefix => $namespace) {
                if (isset($child->getNamespaces(false)[$prefix])) {
                    $name = "{$prefix}:{$name}";
                }
            }
    
            // Si el nodo tiene hijos, llamada recursiva
            if ($child->count()) {
                // Convertir a array recursivamente
                $value = $this->xmlToArray($child, $namespaces);
            } else {
                // Si no tiene hijos, convertir a string
                $value = (string) $child;
            }
    
            // Verificar si el nombre del nodo ya existe en el array
            if (isset($array[$name])) {
                // Si ya existe, convertir el valor en un array (si no lo es)
                if (!is_array($array[$name]) || !isset($array[$name][0])) {
                    $array[$name] = [$array[$name]];
                }
                // Añadir el nuevo valor al array
                $array[$name][] = $value;
            } else {
                // Si no existe, asignar el valor
                $array[$name] = $value;
            }
        }
    
        return $array;
    }    

    private function removeNamespaces($xmlString)
    {
        // Elimina los atributos de namespace (xmlns) dentro de las etiquetas
        $cleanXmlString = preg_replace('/\s+xmlns:[^=]+="[^"]+"/', '', $xmlString);

        return $cleanXmlString;
    }

    public function verifyValues($json, $response) {
        $this->verifyXML($response);
    
        // Paso 1: Extraer el valor del XML de forma dinámica y limpiar namespaces
        $cleanXml = $this->removeNamespaces($response);
        // Paso 2: Convertir el XML a un array
        $xml = new \SimpleXMLElement($cleanXml);
        // Registrar namespaces para poder acceder a los nodos con prefijos
        $namespaces = $xml->getNamespaces(true);
        $xmlArray = $this->xmlToArray($xml, $namespaces);
    
        // Paso 3: Buscar todas las variables con {{}} en el JSON
        preg_match_all('/\{\{(.*?)\}\}/', $json, $matches);
        $variables = $matches[1];
    
        // Paso 4: Verificar si los valores existen en el XML
        foreach ($variables as $variable) {
            // Verificar si el nodo es opcional (tiene '?' al final)
            $isOptional = false;
            if (substr($variable, -1) === '?') {
                $isOptional = true;
                // Eliminar el '?' del final para poder buscar el nodo en el XML
                $variable = rtrim($variable, '?');
            }
    
            // Separar la ruta de nodos por '.' (para acceder a subnodos)
            $path = explode('.', $variable);
    
            // Intentar obtener el valor desde el array del XML
            $value = $xmlArray;
            foreach ($path as $key) {
                if (isset($value[$key])) {
                    $value = $value[$key]; // Continuar buscando el valor en el array
                } else {
                    // Si el nodo no existe y no es opcional, retornamos false
                    if (!$isOptional) {
                        return false; 
                    }
                    // Si es opcional, continuamos sin validar este nodo
                    break;
                }
            }
        }
    
        return true; // Todos los valores requeridos se encontraron en el SOAP
    }    
}