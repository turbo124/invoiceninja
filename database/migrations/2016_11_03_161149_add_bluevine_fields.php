<?php

use Illuminate\Database\Migrations\Migration;

class AddBluevineFields extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->enum('bluevine_status', ['ignored', 'signed_up'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('accounts', function ($table) {
            $table->dropColumn('bluevine_status');
        });
    }
}
