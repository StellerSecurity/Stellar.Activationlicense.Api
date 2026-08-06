<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activationlicenses', function (Blueprint $table) {
            $table->string('idempotency_key', 191)
                ->nullable()
                ->after('subscriptions_days');

            $table->unique(
                ['type', 'idempotency_key'],
                'activationlicenses_type_idem_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('activationlicenses', function (Blueprint $table) {
            $table->dropUnique('activationlicenses_type_idem_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
