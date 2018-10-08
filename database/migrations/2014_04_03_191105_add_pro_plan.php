<?php

use Illuminate\Database\Migrations\Migration;

class AddProPlan extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->date('pro_plan_paid')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('accounts', function ($table) {
            $table->dropColumn('pro_plan_paid');
        });
    }
}
