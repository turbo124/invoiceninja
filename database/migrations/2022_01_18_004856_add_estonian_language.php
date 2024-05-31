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
        $estonia = ['id' => 35, 'name' => 'Estonian', 'locale' => 'et'];

        Language::unguard();
        Language::create($estonia);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
