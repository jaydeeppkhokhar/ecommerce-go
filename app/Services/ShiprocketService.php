<?php

namespace App\Services;

use Botble\Payment\Enums\PaymentMethodEnum;
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

    public function createOrder($order)
    {
        $shipmentProducts = [];
        foreach ($order->products as $orderProduct) {

            $shipmentProducts[] = [
                'name'   => $orderProduct->product_name,
                'sku'    => $orderProduct->options['sku'] ?? 'N/A',
                'units'  => $orderProduct->qty,
                'selling_price' => $orderProduct->price,
            ];
        }

        $payload = [
            "order_id" => (string) $order->code,
            "order_date" => now()->format('Y-m-d'),

            "pickup_location" => "warehouse",

            // Billing
            "billing_customer_name" => $order->address->name,
            "billing_last_name" => "",
            "billing_address" => $order->address->address,
            "billing_city" => $order->address->city,
            "billing_pincode" => $order->address->zip_code,
            "billing_state" => $order->address->state,
            "billing_country" => $order->address->country,
            "billing_email" => $order->address->email,
            "billing_phone" => $order->address->phone,

            // Shipping (REQUIRED even if same)
            "shipping_is_billing" => true,

            // Order Items
            "order_items" => $shipmentProducts,

            // Payment
            "payment_method" => $order->payment->payment_channel == PaymentMethodEnum::COD ? 'COD' : 'Prepaid',

            // Charges
            "sub_total" => (float) $order->amount,

            // Package
            "length" => 10,
            "breadth" => 10,
            "height" => 5,
            "weight" => 0.5
        ];

        Log::info('Creating Shiprocket order payload:', $payload);

        $response = Http::withToken($this->token)
            ->post(config('shiprocket.base_url') . '/orders/create/adhoc', $payload)
            ->json();

        Log::info('Shiprocket create order response', $response);

        return $response;
    }

    public function generateAWB($shipmentId, $order = null)
    {
        Log::info('Creating AWB order', [$shipmentId, $order]);

        if (empty($shipmentId) && $order) {
            $shiprocketOrder = $this->createOrder($order);

            if ($shiprocketOrder && isset($shiprocketOrder['status_code']) && $shiprocketOrder['status_code'] == 1) {
                $shipmentId = $shiprocketOrder['shipment_id'];

                $order->shipment()->update([
                    'shipment_id' => $shiprocketOrder['shipment_id'] ?? $order->shipment->shipment_id,
                ]);
            } else {
                Log::error('Failed to create Shiprocket order for AWB generation', $shiprocketOrder);
                return null;
            }
        }

        $response = Http::withToken($this->token)->post(
            config('shiprocket.base_url') . '/courier/assign/awb',
            ['shipment_id' => $shipmentId]
        )->json();

        Log::info('Shiprocket generate AWB response', $response);

        return $response;
    }

    public function pickupOrder($shipmentId, $order = null)
    {
        Log::info('Creating Pickup order', [$shipmentId, $order]);

        if (empty($shipmentId) && $order) {
            $shiprocketOrder = $this->generateAWB($shipmentId, $order);

            if ($shiprocketOrder && isset($shiprocketOrder['awb_assign_status']) && $shiprocketOrder['awb_assign_status'] == 1) {
                $shipmentCompanyName = $shiprocketOrder['response']['courier_name'] ?? null;
                $awbCode = $shiprocketOrder['response']['awb_code'] ?? null;
                $shipmentId = $shiprocketOrder['response']['shipment_id'] ?? $order->shipment->shipment_id;

                $order->shipment()->update([
                    'shipment_id' => $shipmentId,
                    'shipping_company_name' => $shipmentCompanyName,
                    'tracking_id' => $awbCode,
                ]);
            } else {
                Log::error('Failed to create Shiprocket order for AWB generation', $shiprocketOrder);
                return null;
            }
        }

        $response = Http::withToken($this->token)->post(
            config('shiprocket.base_url') . '/courier/generate/pickup',
            ['shipment_id' => [$shipmentId]]
        )->json();

        Log::info('Shiprocket pickup order response', $response);

        return $response;
    }

    // Cancel Order
    public function cancelOrder($shiprocketOrderIds)
    {
        Log::info('Cancelling Shiprocket order', $shiprocketOrderIds);

        $response = Http::withToken($this->token)->post(
            config('shiprocket.base_url') . '/orders/cancel',
            ['ids' => $shiprocketOrderIds]
        )->json();

        Log::info('Shiprocket cancel order response', $response);
        return $response;
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
