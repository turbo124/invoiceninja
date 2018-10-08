<?php

use Illuminate\Database\Migrations\Migration;

class AllowNullClientCurrency extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('clients', function ($table) {
            //DB::statement('ALTER TABLE `clients` MODIFY `currency_id` INTEGER UNSIGNED NULL;');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
    }
}
