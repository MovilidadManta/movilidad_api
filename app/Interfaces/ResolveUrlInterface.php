<?php

namespace App\Interfaces;

interface ResolveUrlInterface
{
    public function callService(string $url, array $listHeaders, array $data, string $format_send, int $verb_send);
    public function resolveResponse(string $response, string $format_api);
    public function verifyValues($json, $response);
}