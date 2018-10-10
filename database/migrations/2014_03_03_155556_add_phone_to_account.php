<?php

use Illuminate\Database\Migrations\Migration;

class AddPhoneToAccount extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->string('work_phone')->nullable();
            $table->string('work_email')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('accounts', function ($table) {
            $table->dropColumn('work_phone');
            $table->dropColumn('work_email');
        });
    }
}
