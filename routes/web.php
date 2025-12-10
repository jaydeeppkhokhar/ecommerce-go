<?php

use Botble\Ecommerce\Models\Order;
use Illuminate\Support\Facades\Log;

Route::group([
    'prefix' => 'shipping',
    'middleware' => ['api'],
], function (): void {

    Route::post('webhook', function (\Illuminate\Http\Request $request) {

        $awb = $request->awb;
        $status = $request->current_status;

        Log::info('Shiprocket Webhook Received:', $request->all());

        dd($request->all(), 123);

        $order = Order::where('shipping_awb', $awb)->first();

        if ($order) {
            $order->update(['shipping_status' => $status]);
        }

        return response()->json(['success' => true]);
    });
});