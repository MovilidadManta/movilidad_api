<?php

namespace App\Repositories;

use DateTime;
use App\Interfaces\PrevalidacionPeticionesInterface;

class PrevalidacionPeticionesRepository implements PrevalidacionPeticionesInterface
{
    public function __construct()
    {}

    public function resolve($data, $items)
    {
        $list_validation = [];
        foreach ($items as $i) {
            $resolve_validacion = $this->setNestedValue($data, $i->pv_campo, $i->pv_tipo, $i->pv_informacion_adicional);
            if($resolve_validacion)
                $list_validation[] = $resolve_validacion;
        }
        return $list_validation;
    }

    private function setNestedValue($array, $path, $tipo, $informacion_adicional)
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
                if ($subItem[$finalKey]) {
                    return $this->resolvePrevalidacion($path, $tipo, $subItem[$finalKey],$informacion_adicional);
                }
            }
        } else {
            return $this->resolvePrevalidacion($path, $tipo, $array[$finalKey], $informacion_adicional);
        }
    }

    private function resolvePrevalidacion($campo, $tipo, $value, $info_adicional)
    {
        switch ($tipo) {
            case 'in_list':
                $list = explode(",", $info_adicional);
                return !in_array($value, $list) ? "El campo ($campo) debe contener uno de los valores de la lista [$info_adicional]" : null;
            case 'validar_formato_fecha':
                $date = DateTime::createFromFormat($info_adicional, $value);
                // Verifica si la fecha fue correctamente parseada y si no hay errores de formato
                return ($date && $date->format($info_adicional) === $value) || $value == null ? null : "El campo ($campo) no tiene el formato de fecha requerido DD/MM/YYYY HH:mm:ss";
            case 'not_empty':
                return !trim($value) ? "El campo ($campo) no puede ser vacío" : null;
            default:
                return null; // Si no se requiere conversión, retornar el valor sin cambios
        }
    }
}