<?php

use Illuminate\Database\Migrations\Migration;

class AddDanishTranslation extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        //DB::table('languages')->insert(['name' => 'Danish', 'locale' => 'da']);
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        //$language = \App\Models\Language::whereLocale('da')->first();
        //$language->delete();
    }
}
