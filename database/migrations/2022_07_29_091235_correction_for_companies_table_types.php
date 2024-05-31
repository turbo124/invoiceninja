<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedInteger('enabled_expense_tax_rates')->default(0)->change();
        });

        Company::query()->where('enabled_item_tax_rates', true)->cursor()->each(function ($company) {
            $company->enabled_expense_tax_rates = $company->enabled_item_tax_rates;
            $company->save();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
