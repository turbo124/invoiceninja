<?php

use App\Utils\Traits\AppSetup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use AppSetup;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->unsignedInteger('payment_id')->nullable();
        });

        \Illuminate\Support\Facades\Artisan::call('ninja:design-update');

        $this->buildCache(true);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
