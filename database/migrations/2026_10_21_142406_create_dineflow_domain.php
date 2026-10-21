<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_locks', function (Blueprint $t) {
            $t->string('name', 40)->primary();
        });
        DB::table('system_locks')->insert(['name' => 'administrators']);
        Schema::create('users', function (Blueprint $t) {
            $t->bigIncrements('user_id');
            $t->string('name', 150);
            $t->string('username', 100)->unique();
            $t->string('password', 255);
            $t->enum('role', ['admin', 'user'])->default('user');
            $t->enum('department', ['Hostess', 'F&B', 'Marcom']);
            $t->enum('status', ['active', 'inactive'])->default('active');
            $t->dateTime('last_login_at')->nullable();
            $t->string('preferred_locale', 2)->default('ms');
            $t->unsignedInteger('auth_version')->default(1);
            $t->timestamps();
        });
        Schema::create('outlets', function (Blueprint $t) {
            $t->increments('outlet_id');
            $t->string('outlet_code', 30)->unique();
            $t->string('outlet_name', 100)->unique();
            $t->unsignedInteger('sort_order')->default(0);
            $t->enum('status', ['active', 'inactive'])->default('active');
            $t->timestamps();
        });
        Schema::create('outlet_sections', function (Blueprint $t) {
            $t->increments('section_id');
            $t->unsignedInteger('outlet_id');
            $t->string('section_code', 20);
            $t->string('section_name', 100);
            $t->unsignedInteger('sort_order')->default(0);
            $t->enum('status', ['active', 'inactive'])->default('active');
            $t->timestamps();
            $t->unique(['outlet_id', 'section_code']);
            $t->unique(['section_id', 'outlet_id']);
            $t->foreign('outlet_id')->references('outlet_id')->on('outlets')->restrictOnDelete();
        });
        Schema::create('dining_tables', function (Blueprint $t) {
            $t->increments('table_id');
            $t->unsignedInteger('outlet_id');
            $t->unsignedInteger('section_id')->nullable();
            $t->unsignedInteger('table_number')->nullable();
            $t->string('table_code', 20);
            $t->unsignedInteger('sort_order')->default(0);
            $t->enum('status', ['active', 'inactive'])->default('active');
            $t->timestamps();
            $t->unique(['outlet_id', 'table_code']);
            $t->unique(['table_id', 'outlet_id']);
            $t->index(['outlet_id', 'section_id', 'status']);
            $t->foreign('outlet_id')->references('outlet_id')->on('outlets')->restrictOnDelete();
            $t->foreign(['section_id', 'outlet_id'])->references(['section_id', 'outlet_id'])->on('outlet_sections')->restrictOnDelete();
        });
        Schema::create('payment_methods', function (Blueprint $t) {
            $t->increments('payment_method_id');
            $t->string('method_name', 100)->unique();
            $t->enum('status', ['active', 'inactive'])->default('active');
            $t->timestamps();
        });
        Schema::create('bookings', function (Blueprint $t) {
            $t->bigIncrements('booking_id');
            $t->string('booking_code', 30)->unique();
            $t->unsignedInteger('outlet_id');
            $t->date('dining_date');
            $t->string('customer_name', 150);
            $t->string('contact_number', 30);
            $t->unsignedInteger('pax');
            $t->unsignedInteger('table_id');
            $t->unsignedInteger('payment_method_id');
            $t->string('remark', 500)->nullable();
            $t->enum('booking_status', ['booked', 'cancelled'])->default('booked');
            $t->unsignedBigInteger('created_by');
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->unsignedBigInteger('cancelled_by')->nullable();
            $t->dateTime('cancelled_at')->nullable();
            $t->text('cancellation_reason')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->index(['outlet_id', 'dining_date', 'booking_status']);
            $t->foreign('outlet_id')->references('outlet_id')->on('outlets')->restrictOnDelete();
            $t->foreign(['table_id', 'outlet_id'])->references(['table_id', 'outlet_id'])->on('dining_tables')->restrictOnDelete();
            $t->foreign('payment_method_id')->references('payment_method_id')->on('payment_methods')->restrictOnDelete();
            foreach (['created_by', 'updated_by', 'cancelled_by'] as $column) {
                $t->foreign($column)->references('user_id')->on('users')->restrictOnDelete();
            }
        });
        Schema::create('booking_tables', function (Blueprint $t) {
            $t->bigIncrements('booking_table_id');
            $t->unsignedBigInteger('booking_id');
            $t->unsignedInteger('table_id');
            $t->unsignedInteger('pax')->nullable();
            $t->date('dining_date');
            $t->dateTime('released_at')->nullable();
            $t->unsignedInteger('active_table_id')->nullable()->storedAs('CASE WHEN released_at IS NULL THEN table_id ELSE NULL END');
            $t->timestamps();
            $t->unique(['dining_date', 'active_table_id']);
            $t->index(['table_id', 'dining_date']);
            $t->foreign('booking_id')->references('booking_id')->on('bookings')->restrictOnDelete();
            $t->foreign('table_id')->references('table_id')->on('dining_tables')->restrictOnDelete();
        });
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->bigIncrements('activity_id');
            $t->unsignedBigInteger('user_id');
            $t->string('action', 100);
            $t->string('reference_type', 100)->nullable();
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->text('description');
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent', 500)->nullable();
            $t->json('before_values')->nullable();
            $t->json('after_values')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['created_at', 'action']);
            $t->foreign('user_id')->references('user_id')->on('users')->restrictOnDelete();
        });
        Schema::create('booking_sequences', function (Blueprint $t) {
            $t->unsignedSmallInteger('year')->primary();
            $t->unsignedBigInteger('last_number')->default(0);
            $t->timestamp('updated_at')->nullable();
        });
        Schema::create('import_runs', function (Blueprint $t) {
            $t->id();
            $t->string('source_fingerprint', 64);
            $t->string('status', 20)->default('pending');
            $t->json('checkpoint')->nullable();
            $t->json('summary')->nullable();
            $t->dateTime('started_at')->nullable();
            $t->dateTime('finished_at')->nullable();
            $t->timestamps();
        });
        Schema::create('operation_keys', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('operation', 40);
            $t->char('token_hash', 64);
            $t->unsignedBigInteger('booking_id')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['user_id', 'operation', 'token_hash']);
            $t->foreign('user_id')->references('user_id')->on('users')->restrictOnDelete();
            $t->foreign('booking_id')->references('booking_id')->on('bookings')->restrictOnDelete();
        });
        foreach (['UPDATE', 'DELETE'] as $operation) {
            $trigger = 'audit_no_'.strtolower($operation);
            if (DB::getDriverName() === 'mysql') {
                DB::unprepared("CREATE TRIGGER $trigger BEFORE $operation ON activity_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit logs are append-only'");
            } else {
                DB::unprepared("CREATE TRIGGER $trigger BEFORE $operation ON activity_logs BEGIN SELECT RAISE(ABORT, 'Audit logs are append-only'); END");
            }
        }
    }

    public function down(): void
    {
        foreach (['operation_keys', 'import_runs', 'booking_sequences', 'activity_logs', 'booking_tables', 'bookings', 'payment_methods', 'dining_tables', 'outlet_sections', 'outlets', 'users', 'system_locks'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
