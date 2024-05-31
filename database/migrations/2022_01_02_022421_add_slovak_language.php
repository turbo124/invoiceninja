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
        $slovak = ['id' => 34, 'name' => 'Slovak', 'locale' => 'sk'];

        Language::unguard();
        Language::create($slovak);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
