<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_keys', fn (Blueprint $t) => $t->char('request_hash', 64)->nullable());
    }

    public function down(): void
    {
        Schema::table('operation_keys', fn (Blueprint $t) => $t->dropColumn('request_hash'));
    }
};
