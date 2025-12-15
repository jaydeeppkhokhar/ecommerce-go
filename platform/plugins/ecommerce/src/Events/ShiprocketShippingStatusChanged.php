<?php

namespace Botble\Ecommerce\Events;

use App\Services\ShiprocketService;
use Botble\Base\Events\Event;
use Botble\Ecommerce\Enums\OrderHistoryActionEnum;
use Botble\Ecommerce\Enums\ShippingStatusEnum;
use Botble\Ecommerce\Facades\OrderHelper;
use Botble\Ecommerce\Models\Order;
use Botble\Ecommerce\Models\OrderHistory;
use Botble\Ecommerce\Models\Shipment;
use Botble\Ecommerce\Models\ShipmentHistory;
use Carbon\Carbon;
use Illuminate\Queue\SerializesModels;

class ShiprocketShippingStatusChanged extends Event
{
    use SerializesModels;

    public function __construct($shiprocketRequest)
    {
        $shippingOrderId = $shiprocketRequest->sr_order_id ?? null;
        $systemOrderId = $shiprocketRequest->order_id ?? null;
        $currentStatus = $shiprocketRequest->current_status ?? null;

        if (!$shippingOrderId || !$systemOrderId || !$currentStatus) {
            return;
        }

        $updatingSystemStatus = match ($currentStatus) {
            'PICKED UP' => ShippingStatusEnum::PICKED,
            'OUT_FOR_DELIVERY' => ShippingStatusEnum::DELIVERING,
            'DELIVERED' => ShippingStatusEnum::DELIVERED,
            'CANCELLED' => ShippingStatusEnum::CANCELED,
            default => null,
        } ?? null;

        $orderObj = Order::where('code', $systemOrderId)->first();
        $shipmentObj = Shipment::where('shipping_order_id', $shippingOrderId)
            ->orWhere('order_id', $orderObj->id ?? 0)
            ->first();

        if (!$shipmentObj || !$updatingSystemStatus) {
            return;
        }

        $shipmentObj->status = $updatingSystemStatus;
        $shipmentObj->save();

        $shipment = $shipmentObj;

        ShipmentHistory::query()->create([
            'action' => 'update_status',
            'description' => trans('plugins/ecommerce::shipping.changed_shipping_status', [
                'status' => $shipment->status->label(),
            ]),
            'shipment_id' => $shipment->getKey(),
            'order_id' => $shipment->order_id,
            'user_id' => 0,
        ]);

        OrderHistory::query()->create([
            'action' => OrderHistoryActionEnum::UPDATE_SHIPPING_STATUS,
            'description' => trans('plugins/ecommerce::shipping.changed_shipping_status', [
                'status' => $shipment->status->label(),
            ]),
            'order_id' => $shipment->order_id,
            'user_id' => 0,
        ]);

        switch ($updatingSystemStatus) {
            case ShippingStatusEnum::DELIVERED:
                $shipment->date_shipped = Carbon::now();
                $shipment->save();

                $request = request();
                $request->merge(['status' => "delivered"]);

                OrderHelper::shippingStatusDelivered($shipment, $request, 0);

                break;

            case ShippingStatusEnum::CANCELED:
                OrderHistory::query()->create([
                    'action' => OrderHistoryActionEnum::CANCEL_SHIPMENT,
                    'description' => trans('plugins/ecommerce::shipping.shipping_canceled_by'),
                    'order_id' => $shipment->order_id,
                    'user_id' => 0,
                ]);

                break;
        }
    }
}
