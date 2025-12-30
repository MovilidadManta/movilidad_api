<?php

namespace App\Interfaces;

interface OrquestadorRepositoryInterface
{
    public function getConfPeticionByModuloAndPeticion($modulo, $peticion);
    public function getConfPeticionResponseByPIdAndCode($p_id, $code);
    public function registerTransaccion($p_id,$t_uuid,$t_tipo,$t_code,$t_body,$t_body_api, $t_more_information, $ip, $user);
    public function registerLoginUser($l_status ,$l_username,$l_password,$l_info, $ip, $user);
    public function getPostcoversion($p_id);
    public function getPrevalidacion($p_id);
    public function getControlPeticiones($u_id, $p_id);
    public function getIps($u_id, $ip);
}