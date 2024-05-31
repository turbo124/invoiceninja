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
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('show_production_description_dropdown');
        });

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
