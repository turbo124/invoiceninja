<?php

use App\Models\Design;
use App\Utils\Ninja;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Ninja::isHosted()) {
            $design = new Design();

            $design->id = 10;
            $design->name = 'Tech';
            $design->is_custom = false;
            $design->design = '';
            $design->is_active = true;

            $design->save();
        } elseif (Design::count() !== 0) {
            $design = new Design();

            $design->name = 'Tech';
            $design->is_custom = false;
            $design->design = '';
            $design->is_active = true;

            $design->save();
        }

        \Illuminate\Support\Facades\Artisan::call('ninja:design-update');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
