<?php

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
        
        // "verifyBankAccount":false,

        \App\Models\CompanyGateway::withTrashed()->where('gateway_key','b9886f9257f0c6ee7c302f1c74475f6c')
        ->cursor()
        ->each(function ($cg){

            $cg->setConfigField('verifyBankAccount',false);
            $cg->save();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       
    }
};
