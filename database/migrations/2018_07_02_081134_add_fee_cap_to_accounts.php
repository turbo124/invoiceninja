<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFeeCapToAccounts extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('account_gateway_settings', function (Blueprint $table) {
            $table->integer('fee_cap');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('account_gateway_settings', function (Blueprint $table) {
            $table->dropColumn('fee_cap');
        });
    }
}
