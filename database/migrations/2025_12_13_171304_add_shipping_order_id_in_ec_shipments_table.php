<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ec_shipments', function (Blueprint $table) {
            $table->string('shipping_order_id')->nullable()->after('shipment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ec_shipments', function (Blueprint $table) {
            $table->dropColumn('shipping_order_id');
        });
    }
};