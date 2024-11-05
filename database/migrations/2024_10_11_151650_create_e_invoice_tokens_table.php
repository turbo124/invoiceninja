<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('e_invoicing_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('license',64);
            $table->uuid('token',64)->unique()->index();
            $table->string('account_key',64);
            $table->timestamps();
        });
        
        if (Ninja::isSelfHost()) {
            Schema::table('companies', function (Blueprint $table) {
                if (Schema::hasColumn('companies', 'e_invoicing_token')) {
                    $table->dropColumn('e_invoicing_token');
                }
            });
        }

        Schema::table('accounts', function (Blueprint $table) {
            $table->string('e_invoicing_token')->nullable();
        });

    }

    public function down(): void
    {
        
    }
};
