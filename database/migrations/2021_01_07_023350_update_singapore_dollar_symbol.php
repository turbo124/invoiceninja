<?php

use App\Models\Currency;
use App\Utils\Traits\AppSetup;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    use AppSetup;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $currency = Currency::find(13);

        if ($currency) {
            $currency->update(['symbol' => '$']);
        }

        $this->buildCache(true);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
