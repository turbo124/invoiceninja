<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \Illuminate\Support\Facades\Artisan::call('ninja:design-update');

        $t = \App\Models\Country::find(158);

        if ($t) {
            $t->full_name = 'Taiwan';
            $t->name = 'Taiwan';
            $t->save();
        }

        $m = \App\Models\Country::find(807);

        if ($m) {
            $m->full_name = 'Macedonia';
            $m->name = 'Macedonia';
            $m->save();
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
