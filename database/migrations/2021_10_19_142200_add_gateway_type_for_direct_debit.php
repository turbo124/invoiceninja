<?php

use App\Models\GatewayType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $type = new GatewayType();

        $type->id = 18;
        $type->alias = 'direct_debit';
        $type->name = 'Direct Debit';

        $type->save();
    }
};
