<?php

namespace App\Services\Telegram;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TelegramUserLinkService
{
    /**
     * @return array{code:string,expires_at:Carbon}
     */
    public function generateLinkCode(User $user, int $minutes = 10): array
    {
        $expiresAt = now()->addMinutes(max(1, $minutes));
        $code = $this->generateCode();

        $user->forceFill([
            'telegram_link_code_hash' => Hash::make($code),
            'telegram_link_code_expires_at' => $expiresAt,
        ])->save();

        return [
            'code' => $code,
            'expires_at' => $expiresAt,
        ];
    }

    public function linkChatByCode(string $chatId, string $code): ?User
    {
        $normalizedCode = $this->normalizeCode($code);

        if ($normalizedCode === '') {
            return null;
        }

        $candidateUsers = User::query()
            ->whereNotNull('telegram_link_code_hash')
            ->whereNotNull('telegram_link_code_expires_at')
            ->where('telegram_link_code_expires_at', '>=', now())
            ->get();

        foreach ($candidateUsers as $candidateUser) {
            if (!Hash::check($normalizedCode, (string) $candidateUser->telegram_link_code_hash)) {
                continue;
            }

            return DB::transaction(function () use ($chatId, $candidateUser): User {
                User::query()
                    ->where('telegram_chat_id', $chatId)
                    ->where('id', '!=', $candidateUser->id)
                    ->update([
                        'telegram_chat_id' => null,
                        'telegram_linked_at' => null,
                    ]);

                $candidateUser->forceFill([
                    'telegram_chat_id' => $chatId,
                    'telegram_linked_at' => now(),
                    'telegram_link_code_hash' => null,
                    'telegram_link_code_expires_at' => null,
                ])->save();

                return $candidateUser->fresh();
            });
        }

        return null;
    }

    public function findLinkedUserByChatId(string $chatId): ?User
    {
        return User::query()
            ->where('telegram_chat_id', $chatId)
            ->first();
    }

    public function revokeAuthorization(User $user): void
    {
        $user->forceFill([
            'telegram_chat_id' => null,
            'telegram_linked_at' => null,
            'telegram_link_code_hash' => null,
            'telegram_link_code_expires_at' => null,
        ])->save();
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper(trim(str_replace(' ', '', $code)));
    }

    private function generateCode(): string
    {
        return strtoupper(Str::random(8));
    }
}
