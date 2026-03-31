<?php

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramUserLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class TelegramLinkController extends Controller
{
    public function __construct(
        private readonly TelegramUserLinkService $telegramUserLinkService,
    ) {
    }

    public function generateCode(Request $request): RedirectResponse
    {
        $result = $this->telegramUserLinkService->generateLinkCode($request->user());

        return Redirect::route('profile.edit')
            ->with('status', 'telegram-link-code-generated')
            ->with('telegram_link_code', $result['code'])
            ->with('telegram_link_code_expires_at', $result['expires_at']->format('H:i'));
    }
}
