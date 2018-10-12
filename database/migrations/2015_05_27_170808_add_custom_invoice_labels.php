<?php

use Illuminate\Database\Migrations\Migration;

class AddCustomInvoiceLabels extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->text('invoice_labels')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('accounts', function ($table) {
            $table->dropColumn('invoice_labels');
        });
    }
}
