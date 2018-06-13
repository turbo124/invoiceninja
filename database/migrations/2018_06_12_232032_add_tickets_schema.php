<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTicketsSchema extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tickets', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('client_id');
            $table->unsignedInteger('agent_id');
            $table->unsignedInteger('public_id');
            $table->unsignedInteger('priority_id')->default(1);
            $table->boolean('is_deleted')->default(0);
            $table->boolean('is_internal')->default(0);
            $table->unsignedInteger('status_id');
            $table->unsignedInteger('type_id');
            $table->text('subject');
            $table->text('description');
            $table->longtext('tags');
            $table->longtext('private_notes');
            $table->longtext('ccs');
            $table->string('ip_address', 255);
            $table->string('contact_key', 255);
            $table->dateTime('due_date');
            $table->dateTime('closed');
            $table->dateTime('reopened');
        });


        Schema::table('tickets', function ($table) {
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('type_id')->references('id')->on('ticket_type');
            $table->foreign('status_id')->references('id')->on('ticket_status');
        });


        Schema::create('ticket_type', function ($table) {
            $table->increments('id');
            $table->text('description');
        });

        Schema::create('ticket_status', function ($table) {
            $table->increments('id');
            $table->string('name', 255);
            $table->string('trigger_column', 255);
            $table->text('trigger_threshold');
            $table->string('color', 255);
            $table->text('description');
            $table->unsignedInteger('type_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('public_id');
            $table->unsignedInteger('sort_order');
            $table->boolean('is_deleted')->default(0);
        });

        Schema::table('ticket_status', function ($table) {
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('type_id')->references('id')->on('ticket_type');
        });


        Schema::create('ticket_relations', function ($table) {
            $table->increments('id');
            $table->string('entity', 255);
            $table->unsignedInteger('entity_id');
            $table->unsignedInteger('ticket_id');
        });

        Schema::table('ticket_relations', function ($table) {
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');
        });


        Schema::create('ticket_templates', function ($table) {
            $table->increments('id');
            $table->string('name', 255);
            $table->text('description');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('public_id');
        });

        Schema::table('ticket_templates', function ($table) {
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });


        Schema::create('ticket_comments', function ($table) {
            $table->increments('id');
            $table->text('description');
            $table->string('contact_key', 255);
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('ticket_id');
            $table->unsignedInteger('public_id');
            $table->boolean('is_deleted')->default(0);
        });

        Schema::table('ticket_comments', function ($table) {
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('tickets');
        Schema::drop('ticket_type');
        Schema::drop('ticket_status');
        Schema::drop('ticket_templates');
        Schema::drop('ticket_relations');
        Schema::drop('ticket_comments');

    }
}
