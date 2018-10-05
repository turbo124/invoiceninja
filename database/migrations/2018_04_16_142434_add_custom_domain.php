<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

class AddCustomDomain extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->boolean('is_custom_domain')->default(false);
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
