<?php

namespace Botble\Ecommerce\Events;

use App\Services\ShiprocketService;
use Botble\ACL\Models\User;
use Botble\Base\Events\Event;
use Botble\Ecommerce\Models\Order;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Queue\SerializesModels;

class OrderConfirmedEvent extends Event
{
    use SerializesModels;

    public function __construct(public Order $order, public User|Authenticatable|null $confirmedBy = null)
    {
        $shiprocket = app(ShiprocketService::class);
        $shiprocketOrder = $shiprocket->createOrder($order);

        if ($shiprocketOrder && isset($shiprocketOrder['status_code']) && $shiprocketOrder['status_code'] == 1) {
            $order->shipment()->update([
                'shipping_order_id' => $shiprocketOrder['order_id'] ?? $order->shipment->shipping_order_id,
                'shipment_id' => $shiprocketOrder['shipment_id'] ?? $order->shipment->shipment_id,
            ]);
        }
    }
}