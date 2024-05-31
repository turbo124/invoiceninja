<?php

use App\Models\Language;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Language::unguard();

        if (! Language::find(39)) {
            $hungarian = ['id' => 39, 'name' => 'Hungarian', 'locale' => 'hu'];
            Language::create($hungarian);
        }

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        //
    }
};
