<?php

use Illuminate\Database\Migrations\Migration;

class EnableForcingJspdf extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('users', function ($table) {
            $table->boolean('force_pdfjs')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('users', function ($table) {
            $table->dropColumn('force_pdfjs');
        });
    }
}
