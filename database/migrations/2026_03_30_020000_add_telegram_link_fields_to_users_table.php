<?php

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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('telegram_chat_id', 64)->nullable()->unique()->after('remember_token');
            $table->string('telegram_link_code_hash')->nullable()->after('telegram_chat_id');
            $table->timestamp('telegram_link_code_expires_at')->nullable()->after('telegram_link_code_hash');
            $table->timestamp('telegram_linked_at')->nullable()->after('telegram_link_code_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_telegram_chat_id_unique');
            $table->dropColumn([
                'telegram_chat_id',
                'telegram_link_code_hash',
                'telegram_link_code_expires_at',
                'telegram_linked_at',
            ]);
        });
    }
};
