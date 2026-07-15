<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class RajaOngkirService
{
    protected string $apiKey;
    protected string $baseUrl;
    protected string $originCityId;

    public function __construct()
    {
        $this->apiKey = Setting::getValue('rajaongkir_api_key', '');
        $this->baseUrl = app()->environment('production')
            ? 'https://pro.rajaongkir.com/api/'
            : 'https://api.rajaongkir.com/starter/';
        $this->originCityId = Setting::getValue('rajaongkir_origin_city', '151');
    }

    public function getProvinces(): array
    {
        $response = Http::withHeaders([
            'key' => $this->apiKey,
        ])->get($this->baseUrl . 'province');

        if ($response->successful()) {
            $data = $response->json();
            return $data['rajaongkir']['results'] ?? [];
        }

        throw new \Exception('Failed to fetch provinces: ' . $response->body());
    }

    public function getCities(string $provinceId): array
    {
        $response = Http::withHeaders([
            'key' => $this->apiKey,
        ])->get($this->baseUrl . 'city', [
            'province' => $provinceId,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['rajaongkir']['results'] ?? [];
        }

        throw new \Exception('Failed to fetch cities: ' . $response->body());
    }

    public function getShippingCost(string $destinationCityId, int $weight, string $courier): array
    {
        $response = Http::withHeaders([
            'key' => $this->apiKey,
        ])->post($this->baseUrl . 'cost', [
            'origin' => $this->originCityId,
            'destination' => $destinationCityId,
            'weight' => max($weight, 1),
            'courier' => strtolower($courier),
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $results = $data['rajaongkir']['results'] ?? [];

            if (empty($results)) {
                return [];
            }

            $costs = [];
            foreach ($results[0]['costs'] ?? [] as $cost) {
                $costs[] = [
                    'service' => $cost['service'],
                    'description' => $cost['description'],
                    'cost' => $cost['cost'][0]['value'] ?? 0,
                    'etd' => $cost['cost'][0]['etd'] ?? '',
                ];
            }

            return $costs;
        }

        throw new \Exception('Failed to fetch shipping cost: ' . $response->body());
    }

    public function trackShipment(string $waybill, string $courier): array
    {
        $response = Http::withHeaders([
            'key' => $this->apiKey,
        ])->post($this->baseUrl . 'waybill', [
            'waybill' => $waybill,
            'courier' => strtolower($courier),
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['rajaongkir']['result'] ?? [];
        }

        throw new \Exception('Failed to track shipment: ' . $response->body());
    }
}
