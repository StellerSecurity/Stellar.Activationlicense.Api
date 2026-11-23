<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activationlicenses', function (Blueprint $table) {
            $table->unsignedBigInteger('reseller_user_id')
                ->nullable()
                ->after('idempotency_key');

            $table->index(
                'reseller_user_id',
                'activationlicenses_reseller_user_id_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('activationlicenses', function (Blueprint $table) {
            $table->dropIndex('activationlicenses_reseller_user_id_index');
            $table->dropColumn('reseller_user_id');
        });
    }
};
