<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

class AddGatewayFeeCalcOption extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('account_gateway_settings', function ($table) {
            $table->boolean('adjust_fee_percent')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        //
    }
}
