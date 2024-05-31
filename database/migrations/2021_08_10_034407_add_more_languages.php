<?php

use App\Models\Language;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Language::unguard();

        $language = Language::find(30);

        if (! $language) {
            Language::create(['id' => 30, 'name' => 'Arabic', 'locale' => 'ar']);
        }

        $language = Language::find(31);

        if (! $language) {
            Language::create(['id' => 31, 'name' => 'Persian', 'locale' => 'fa']);
        }

        $language = Language::find(32);

        if (! $language) {
            Language::create(['id' => 32, 'name' => 'Latvian', 'locale' => 'lv_LV']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
