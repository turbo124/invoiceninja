<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDartFormatColumn extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('date_formats', function (Blueprint $table) {
            $table->string('format_dart');
        });
        Schema::table('datetime_formats', function (Blueprint $table) {
            $table->string('format_dart');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('date_formats', function (Blueprint $table) {
            $table->dropColumn('format_dart');
        });
        Schema::table('datetime_formats', function (Blueprint $table) {
            $table->dropColumn('format_dart');
        });
    }
}
