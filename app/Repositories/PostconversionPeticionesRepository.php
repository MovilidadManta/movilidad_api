<?php

namespace App\Repositories;

use App\Interfaces\PostconversionPeticionesInterface;

class PostconversionPeticionesRepository implements PostconversionPeticionesInterface
{
    public function __construct()
    {}

    public function resolve($data, $items)
    {
        foreach ($items as $i) {
            $this->setNestedValue($data, $i->pc_campo, $i->pc_tipo, $i->pc_campo_afectado);
        }
        return $data;
    }

    private function setNestedValue(&$array, $path, $tipo, $campo_afectado)
    {
        // Dividir la ruta en segmentos, por ejemplo "bloqueos.observacion" en ["bloqueos", "observacion"]
        $keys = explode('.', $path);
        $originalArray = &$array;

        // Recorrer el array usando los segmentos
        while (count($keys) > 1) {
            $key = array_shift($keys);

            // Si el key actual no existe o no es un array, no hay nada que hacer
            if (!isset($array[$key])) {
                return;
            }

            // Si el valor es un array, hacer referencia a cada elemento en caso de que haya subarrays
            if (is_array($array[$key])) {
                $array = &$array[$key];
            } else {
                return; // No hay un array para continuar el recorrido
            }
        }

        // Obtener el último key y aplicarle el valor deseado
        $finalKey = array_shift($keys);

        // Si el último key apunta a un array, asignar el valor a cada elemento
        if (isset($array[0]) && is_array($array)) {
            foreach ($array as &$subItem) {
                if (isset($subItem[$finalKey])) {
                    $subItem[$finalKey] = $this->resolveConversion($tipo, $subItem[$finalKey]);
                }
            }
        } elseif (isset($array[$finalKey])) {
            $array[$finalKey] = $this->resolveConversion($tipo, $array[$finalKey]);
        }

        // Restaurar la referencia original del array para que se refleje en $data
        $array = &$originalArray;
    }

    private function onlyNestedValue($array, $path, $tipo, $campo_afectado, $info_adicional)
    {
        // Dividir la ruta en segmentos, por ejemplo "bloqueos.observacion" en ["bloqueos", "observacion"]
        $keys = explode('.', $path);
        $originalArray = $array;

        // Recorrer el array usando los segmentos
        while (count($keys) > 1) {
            $key = array_shift($keys);

            // Si el key actual no existe o no es un array, no hay nada que hacer
            if (!isset($array[$key])) {
                return;
            }

            // Si el valor es un array, hacer referencia a cada elemento en caso de que haya subarrays
            if (is_array($array[$key])) {
                $array = $array[$key];
            } else {
                return; // No hay un array para continuar el recorrido
            }
        }

        // Obtener el último key y aplicarle el valor deseado
        $finalKey = array_shift($keys);

        // Si el último key apunta a un array, asignar el valor a cada elemento
        if (isset($array[0]) && is_array($array)) {
            foreach ($array as $subItem) {
                if (isset($subItem[$finalKey])) {
                    return $this->resolveConversion($tipo, $subItem[$finalKey]);
                }
            }
        } elseif (isset($array[$finalKey])) {
            return $this->resolveConversion($tipo, $array[$finalKey]);
        }
    }

    private function resolveConversion($tipo, $value, $info_adicional = "")
    {
        switch ($tipo) {
            case 'decode_base64':
                return base64_decode($value);
            
            case 'xml_to_array':
                // Convierte el XML en un array, retornando el valor original si falla
                $xmlObject = simplexml_load_string($value, "SimpleXMLElement", LIBXML_NOCDATA);
                if ($xmlObject === false) {
                    return $value; // Retorna el valor original si el XML no es válido
                }
                return json_decode(json_encode($xmlObject), true);
            
            default:
                return $value; // Si no se requiere conversión, retornar el valor sin cambios
        }
    }
}