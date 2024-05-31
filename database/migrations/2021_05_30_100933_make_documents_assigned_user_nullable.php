<?php

use App\Libraries\MultiDB;
use App\Models\Document;
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
        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedInteger('assigned_user_id')->nullable()->change();
        });

        Document::query()->where('assigned_user_id', 0)->update(['assigned_user_id' => null]);

        if (config('ninja.db.multi_db_enabled')) {
            foreach (MultiDB::$dbs as $db) {
                Document::on($db)->where('assigned_user_id', 0)->update(['assigned_user_id' => null]);
            }
        } else {
            Document::query()->where('assigned_user_id', 0)->update(['assigned_user_id' => null]);
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
