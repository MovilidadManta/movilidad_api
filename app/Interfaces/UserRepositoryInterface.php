<?php

namespace App\Interfaces;

interface UserRepositoryInterface
{
    public function validateLogin($username, $password, $ip, $user);
    public function ModelUserByUsername($username);
    public function ModelUserById($u_id);
}