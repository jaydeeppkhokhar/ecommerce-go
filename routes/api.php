<?php

use Botble\Ecommerce\Events\ShiprocketShippingStatusChanged;
use Botble\Ecommerce\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::any('/shipping/webhook', function (Request $request) {
    Log::info('Shiprocket Webhook API Received:', $request->all());

    event(new ShiprocketShippingStatusChanged($request));

    return response()->json(['success' => true]);
});
