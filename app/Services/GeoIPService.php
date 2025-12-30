<?php

namespace App\Services;
use GeoIp2\Database\Reader;
use Illuminate\Support\Facades\Log;

class GeoIPService
{
    protected $reader;

    public function __construct()
    {
        // Ruta al archivo de la base de datos
        $this->reader = new Reader(storage_path('app/GeoLite2-City.mmdb'));
    }

    public function getGeoData(string $ip)
    {
        try {
            $record = $this->reader->city($ip);

            return [
                'iso_code' => optional($record->country)->isoCode,
                'country' => optional($record->country)->name,
                'region' => optional($record->mostSpecificSubdivision)->name,
                'city' => optional($record->city)->name,
                'latitude' => optional($record->location)->latitude,
                'longitude' => optional($record->location)->longitude,
                'postal_code' => optional($record->postal)->code,
                'network' => optional($record->traits)->network,
                'time_zone' => optional($record->location)->timeZone,
                'accuracy_radius' => optional($record->location)->accuracyRadius,
                'continent' => optional($record->continent)->name,
                'continent_code' => optional($record->continent)->code,
                'asn' => optional($record->traits)->autonomousSystemNumber,
                'asn_organization' => optional($record->traits)->autonomousSystemOrganization,
                'user_type' => optional($record->traits)->userType,
                'is_anonymous' => optional($record->traits)->isAnonymous,
                'is_anonymous_proxy' => optional($record->traits)->isAnonymousProxy,
                'is_satellite_provider' => optional($record->traits)->isSatelliteProvider,
            ];            
        } catch (\Exception $e) {
            Log::error('Error Data: ' . $e->getMessage(), [
                'exception' => $e,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Manejar errores, por ejemplo, si la IP no se encuentra en la base de datos
            return null;
        }
    }
}