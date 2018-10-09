<?php

use Illuminate\Database\Migrations\Migration;

class AddAcceptedCreditCardsToAccountGateways extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('account_gateways', function ($table) {
            $table->unsignedInteger('accepted_credit_cards')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('account_gateways', function ($table) {
            $table->dropColumn('accepted_credit_cards');
        });
    }
}
