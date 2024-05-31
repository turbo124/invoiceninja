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

        if (! Language::find(33)) {
            $serbian = ['id' => 33, 'name' => 'Serbian', 'locale' => 'sr'];
            Language::create($serbian);
        }

        if (! Language::find(34)) {
            $slovak = ['id' => 34, 'name' => 'Slovak', 'locale' => 'sk'];
            Language::create($slovak);
        }

        if (! Language::find(35)) {
            $estonia = ['id' => 35, 'name' => 'Estonian', 'locale' => 'et'];
            Language::create($estonia);
        }

        if (! Language::find(36)) {
            $bulgarian = ['id' => 36, 'name' => 'Bulgarian', 'locale' => 'bg'];
            Language::create($bulgarian);
        }

        if (! Language::find(37)) {
            $hebrew = ['id' => 37, 'name' => 'Hebrew', 'locale' => 'he'];
            Language::create($hebrew);
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
