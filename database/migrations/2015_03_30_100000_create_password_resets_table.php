<?php

use Illuminate\Database\Migrations\Migration;

class CreatePasswordResetsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::rename('password_reminders', 'password_resets');
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::rename('password_resets', 'password_reminders');
    }
}
