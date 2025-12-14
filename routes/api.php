<?php

use Botble\Ecommerce\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::any('/shipping/webhook', function (Request $request) {

    $awb = $request->awb;
    $status = $request->current_status;

    Log::info('Shiprocket Webhook API Received:', $request->all());

    dd($request->all(), 123);

    $order = Order::where('shipping_awb', $awb)->first();

    if ($order) {
        $order->update(['shipping_status' => $status]);
    }

    return response()->json(['success' => true]);
});
