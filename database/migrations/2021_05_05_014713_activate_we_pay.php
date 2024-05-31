<?php

use App\Models\Gateway;
use App\Utils\Ninja;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Gateway::count() >= 1 && Ninja::isHosted()) {
            Gateway::query()->whereIn('id', [49])->update(['visible' => true]);
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
