<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class ErrorHandlingService
{
    public static function handle($e)
    {
        if ($e instanceof ValidationException) {
            $errors = $e->validator->errors()->all();
            return jsonResponse([], 422, 'Errores de validación', ['errors' => $errors]);
        } else {
           // return jsonResponse([], 500, 'Error inesperado', ['error' => $e->getMessage()]);
           return jsonResponse([], 500, 'Error inesperado', []);
        }
    }
}