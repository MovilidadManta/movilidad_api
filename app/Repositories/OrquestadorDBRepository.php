<?php

namespace App\Repositories;

use App\Interfaces\OrquestadorRepositoryInterface;
use Illuminate\Support\Facades\DB;

class OrquestadorDBRepository implements OrquestadorRepositoryInterface
{
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

    public function getConfPeticionByModuloAndPeticion($modulo, $peticion)
    {
        return $this->executeQuery("SELECT p_id,p_modulo,p_peticion,p_verb_send,ps_url, 
        orquestador.pgp_sym_decrypt(ps_headers_api::bytea, '" . env('DB_USERS_ENCRYPTION_KEY') . "') as ps_headers_api,
        p_request,p_request_api,ps_format_api,p_estado,pa_id 
        FROM view_tbl_conf_peticiones WHERE p_modulo = ? AND p_peticion = ?", [$modulo, $peticion]);
    }

    public function getConfPeticionResponseByPIdAndCode($p_id, $code)
    {
        return $this->executeQuery("SELECT * FROM view_tbl_conf_peticiones_response WHERE p_id = ? AND r_codigo = ? ORDER BY r_orden", [$p_id, $code]);
    }

    public function registerTransaccion($p_id, $t_uuid, $t_tipo, $t_code, $t_body, $t_body_api, $t_more_information, $ip, $user)
    {
        $json = json_encode([
            [
                'p_id' => $p_id,
                't_uuid' => $t_uuid,
                't_tipo' => $t_tipo,
                't_code' => $t_code,
                't_body' => $t_body,
                't_body_api' => $t_body_api,
                'more_information' => $t_more_information
            ]
        ]);

        $result = $this->executeQuery('select procedimiento_registrar_tbl_transacciones(?,?,?)', [$json, $ip, $user]);
        return !empty($result) ? $result[0]->procedimiento_registrar_tbl_transacciones : -1;
    }

    public function getPostcoversion($p_id)
    {
        return $this->executeQuery("SELECT * FROM tbl_conf_peticiones_postconversion WHERE pc_estado = TRUE AND p_id = ? ORDER BY pc_orden", [$p_id]);
    }

    public function getPrevalidacion($p_id)
    {
        return $this->executeQuery("SELECT * FROM tbl_conf_peticiones_prevalidacion WHERE pv_estado = TRUE AND p_id = ? ORDER BY pv_orden", [$p_id]);
    }

    public function getControlPeticiones($u_id, $p_id)
    {
        return $this->executeQuery("SELECT * FROM tbl_conf_control_peticiones_users WHERE u_id = ? AND p_id = ?", [$u_id, $p_id]);
    }

    public function getIps($u_id, $ip)
    {
        return $this->executeQuery("SELECT * FROM tbl_users_ip WHERE u_id = ? AND ui_ip = ?", [$u_id, $ip]);
    }

    public function registerLoginUser($l_status, $l_username, $l_password, $l_info, $ip, $user)
    {
        $json = json_encode([
            [
                'l_status' => $l_status,
                'l_username' => $l_username,
                'l_password' => $l_password,
                'l_info' => $l_info,
                'key' => env('DB_USERS_ENCRYPTION_KEY')
            ]
        ]);

        $result = $this->executeQuery('select procedimiento_registrar_tbl_login_users(?,?,?)', [$json, $ip, $user]);
        return !empty($result) ? $result[0]->procedimiento_registrar_tbl_login_users : -1;
    }
}