<?php

namespace Botble\Ecommerce\Events;

use App\Services\ShiprocketService;
use Botble\Base\Events\Event;
use Botble\Ecommerce\Enums\ShippingStatusEnum;
use Botble\Ecommerce\Models\Shipment;
use Illuminate\Queue\SerializesModels;

class ShippingStatusChanged extends Event
{
    use SerializesModels;

    public function __construct(public Shipment $shipment, public array $previousShipment = [])
    {
        switch ($shipment->status) {
            case ShippingStatusEnum::ARRANGE_SHIPMENT:
                $shiprocket = app(ShiprocketService::class);

                $shiprocketOrder = $shiprocket->generateAWB($shipment->shipment_id, empty($shipment->shipment_id) ? $shipment->order : null);

                if ($shiprocketOrder && isset($shiprocketOrder['awb_assign_status']) && $shiprocketOrder['awb_assign_status'] == 1) {
                    $shipmentCompanyName = $shiprocketOrder['response']['data']['courier_name'] ?? null;
                    $awbCode = $shiprocketOrder['response']['data']['awb_code'] ?? null;
                    $pickupScheduledDate = $shiprocketOrder['response']['data']['pickup_scheduled_date'] ?? null;

                    $shipment->shipping_company_name = $shipmentCompanyName;
                    $shipment->tracking_id = $awbCode;
                    $shipment->tracking_link = $shiprocket->trackPublicUrl($awbCode);
                    $shipment->estimate_date_shipped = $pickupScheduledDate;

                    $shipment->save();
                }

                break;

            case ShippingStatusEnum::READY_TO_BE_SHIPPED_OUT:
                $shiprocket = app(ShiprocketService::class);

                $shiprocketOrder = $shiprocket->pickupOrder($shipment->shipment_id, empty($shipment->shipment_id) ? $shipment->order : null);

                if ($shiprocketOrder && isset($shiprocketOrder['pickup_status']) && $shiprocketOrder['pickup_status'] == 1) {
                    $pickupScheduledDate = $shiprocketOrder['response']['pickup_scheduled_date'] ?? null;

                    $shipment->estimate_date_shipped = $pickupScheduledDate;

                    $shipment->save();
                }

                break;
        }
    }
}