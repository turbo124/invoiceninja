<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table)
        {
            $table->string('xero_oauth_user_id',100)->nullable();
            $table->text('xero_oauth_access_token')->nullable();
            $table->text('xero_oauth_refresh_token')->nullable();
        });

        Schema::create('xero_tenants', function (Blueprint $table)
        {
            $table->id();
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('user_id');
            $table->boolean('is_deleted')->default(false);

            $table->string('tenant_id',191)->nullable();
            $table->string('tenant_name',191)->nullable();
            $table->string('tenant_type', 191)->nullable();

            $table->timestamps(6);
            $table->unique(['account_id', 'tenant_id']);

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');

        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
