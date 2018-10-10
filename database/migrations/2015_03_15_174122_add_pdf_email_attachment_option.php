<?php

use Illuminate\Database\Migrations\Migration;

class AddPdfEmailAttachmentOption extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->smallInteger('pdf_email_attachment')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('accounts', function ($table) {
            $table->dropColumn('pdf_email_attachment');
        });
    }
}
