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
        $serbian = ['id' => 33, 'name' => 'Serbian', 'locale' => 'sr'];

        Language::unguard();
        Language::create($serbian);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
