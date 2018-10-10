<?php

use Illuminate\Database\Migrations\Migration;

class AddIpToActivity extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('activities', function ($table) {
            $table->string('ip')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('activities', function ($table) {
            $table->dropColumn('ip');
        });
    }
}
