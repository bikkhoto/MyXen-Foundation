<?php

namespace App\Services\Notifications\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Telegram Service
 *
 * Handles sending messages via Telegram Bot API.
 */
class TelegramService
{
    /**
     * Telegram Bot Token
     *
     * @var string
     */
    protected string $botToken;

    /**
     * Telegram API Base URL
     *
     * @var string
     */
    protected string $apiUrl;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->botToken = config('notifications.telegram.bot_token');

        if (empty($this->botToken)) {
            throw new Exception('Telegram Bot Token is not configured. Please set TELEGRAM_BOT_TOKEN in .env');
        }

        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    /**
     * Send a message via Telegram.
     *
     * @param string $chatId Telegram chat ID
     * @param string $message Message text
     * @param string $parseMode Message parse mode (HTML, Markdown, MarkdownV2)
     * @return array Response from Telegram API
     * @throws Exception
     */
    public function send(string $chatId, string $message, string $parseMode = 'HTML'): array
    {
        $url = "{$this->apiUrl}/sendMessage";

        try {
            $response = Http::post($url, [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => $parseMode,
            ]);

            if ($response->failed()) {
                $error = $response->json('description') ?? 'Unknown error';
                throw new Exception("Telegram API error: {$error}");
            }

            $data = $response->json();

            if (!($data['ok'] ?? false)) {
                $error = $data['description'] ?? 'Unknown error';
                throw new Exception("Telegram API returned not ok: {$error}");
            }

            Log::info('Message sent via Telegram', [
                'chat_id' => $chatId,
                'message_id' => $data['result']['message_id'] ?? null,
            ]);

            return $data;
        } catch (Exception $e) {
            Log::error('Telegram send failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to send Telegram message: ' . $e->getMessage());
        }
    }

    /**
     * Get bot information.
     *
     * @return array
     * @throws Exception
     */
    public function getMe(): array
    {
        $url = "{$this->apiUrl}/getMe";

        try {
            $response = Http::get($url);

            if ($response->failed() || !($response->json('ok') ?? false)) {
                $error = $response->json('description') ?? 'Unknown error';
                throw new Exception("Telegram API error: {$error}");
            }

            return $response->json('result');
        } catch (Exception $e) {
            throw new Exception('Failed to get bot info: ' . $e->getMessage());
        }
    }
}
