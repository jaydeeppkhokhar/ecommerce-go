<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShiprocketService
{
    protected $token;

    public function __construct()
    {
        $this->token = $this->login();
    }

    public function login()
    {
        $response = Http::post(config('shiprocket.base_url') . '/auth/login', [
            'email' => config('shiprocket.email'),
            'password' => config('shiprocket.password')
        ]);

        return $response['token'] ?? null;
    }

    public function createOrder($data)
    {
        Log::info('Creating Shiprocket order', $data);

        return Http::withToken($this->token)
            ->post(config('shiprocket.base_url') . '/orders/create/adhoc', $data)
            ->json();
    }

    public function generateAWB($shipmentId)
    {
        Log::info('Creating AWB order', [$shipmentId]);

        return Http::withToken($this->token)->post(
            config('shiprocket.base_url') . '/courier/assign/awb',
            ['shipment_id' => $shipmentId]
        )->json();
    }

    public function pickupOrder($shipmentId)
    {
        Log::info('Creating Pickup order', [$shipmentId]);

        return Http::withToken($this->token)->post(
            config('shiprocket.base_url') . '/courier/generate/pickup',
            ['shipment_id' => [$shipmentId]]
        )->json();
    }

    public function track($awb)
    {
        return Http::withToken($this->token)
            ->get(config('shiprocket.base_url') . "/courier/track/awb/$awb")
            ->json();
    }

    public function trackPublicUrl($awb)
    {
        return "https://shiprocket.co/tracking/{$awb}";
    }
}