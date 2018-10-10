<?php

use Illuminate\Database\Migrations\Migration;

class AddClientViewCss extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->text('client_view_css')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('accounts', function ($table) {
            $table->dropColumn('client_view_css');
        });
    }
}
