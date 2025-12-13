<?php

namespace Botble\Ecommerce\Events;

use App\Services\ShiprocketService;
use Botble\Base\Events\Event;
use Botble\Ecommerce\Models\Order;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OrderCancelledEvent extends Event
{
    use SerializesModels;

    public function __construct(
        public Order $order,
        public ?string $reason = null,
        public ?string $reasonDescription = null
    ) {
        $shippingOrderID = $order->shipment->shipping_order_id ?? null;
        if (!$shippingOrderID) {
            return;
        }

        Log::info('Cancelling Shiprocket order [' . $shippingOrderID . '] ');
        $shiprocket = app(ShiprocketService::class);
        $shiprocket->cancelOrder([$shippingOrderID]);
    }
}