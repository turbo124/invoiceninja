<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

class LimitNotifications extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('users', function ($table) {
            $table->boolean('only_notify_owned')->nullable()->default(false);
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
