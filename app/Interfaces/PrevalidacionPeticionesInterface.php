<?php

namespace App\Interfaces;

interface PrevalidacionPeticionesInterface
{
    public function resolve($data, $items);
}