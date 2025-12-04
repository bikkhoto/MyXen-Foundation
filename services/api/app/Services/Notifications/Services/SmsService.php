<?php

namespace App\Services\Notifications\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS Service
 *
 * Handles SMS sending via Twilio API.
 */
class SmsService
{
    /**
     * Twilio Account SID
     *
     * @var string
     */
    protected string $accountSid;

    /**
     * Twilio Auth Token
     *
     * @var string
     */
    protected string $authToken;

    /**
     * Twilio From Number
     *
     * @var string
     */
    protected string $fromNumber;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->accountSid = config('notifications.sms.twilio.account_sid');
        $this->authToken = config('notifications.sms.twilio.auth_token');
        $this->fromNumber = config('notifications.sms.twilio.from_number');

        if (empty($this->accountSid) || empty($this->authToken) || empty($this->fromNumber)) {
            throw new Exception('Twilio credentials are not configured. Please set TWILIO_SID, TWILIO_TOKEN, and TWILIO_FROM in .env');
        }
    }

    /**
     * Send SMS via Twilio.
     *
     * @param string $to Phone number in E.164 format (e.g., +1234567890)
     * @param string $message Message body
     * @return array Response from Twilio API
     * @throws Exception
     */
    public function send(string $to, string $message): array
    {
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";

        try {
            $response = Http::asForm()
                ->withBasicAuth($this->accountSid, $this->authToken)
                ->post($url, [
                    'From' => $this->fromNumber,
                    'To' => $to,
                    'Body' => $message,
                ]);

            if ($response->failed()) {
                $error = $response->json('message') ?? 'Unknown error';
                throw new Exception("Twilio API error: {$error}");
            }

            $data = $response->json();

            Log::info('SMS sent via Twilio', [
                'to' => $to,
                'sid' => $data['sid'] ?? null,
                'status' => $data['status'] ?? null,
            ]);

            return $data;
        } catch (Exception $e) {
            Log::error('SMS send failed', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to send SMS: ' . $e->getMessage());
        }
    }
}
