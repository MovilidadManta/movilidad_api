<?php

namespace App\Repositories;

use App\Interfaces\UserRepositoryInterface;
use App\Models\Users;
use Illuminate\Support\Facades\DB;

class UserDBRepository implements UserRepositoryInterface
{
    protected $model;

    public function __construct()
    {}

    private function executeQuery($query, $params = [])
    {
        $connection = DB::connection();
        try {
            return $connection->select($query, $params);
        } finally {
            $connection->disconnect(); // Cierra la conexión después de ejecutar la consulta
        }
    }

    public function validateLogin($username, $password, $ip, $user)
    {
        $json = json_encode([
            [
                'username' => $username,
                'password' => $password,
                'key' => env('DB_USERS_ENCRYPTION_KEY')
            ]
        ]);

        $result = $this->executeQuery('select procedimiento_validar_log_in(?,?,?)', [$json, $ip, $user]);
        return !empty($result) ? $result[0]->procedimiento_validar_log_in : -1;
    }

    public function ModelUserByUsername($username)
    {
        return Users::where('u_username', $username)->first();
    }

    public function ModelUserById($u_id)
    {
        return Users::where('u_id', $u_id)->first();
    }
}