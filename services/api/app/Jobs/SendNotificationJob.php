<?php

namespace App\Jobs;

use App\Mail\GenericNotificationMail;
use App\Models\Notification;
use App\Services\Notifications\Services\SmsService;
use App\Services\Notifications\Services\TelegramService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Send Notification Job
 *
 * Dispatches notifications via multiple channels (email, SMS, Telegram, push)
 * with retry logic and exponential backoff.
 */
class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The notification to send.
     *
     * @var Notification
     */
    public Notification $notification;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public int $maxExceptions = 3;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * Implements exponential backoff: 2^attempt seconds.
     *
     * @return int
     */
    public function backoff(): int
    {
        return (int) pow(2, $this->attempts());
    }

    /**
     * Create a new job instance.
     *
     * @param Notification $notification
     */
    public function __construct(Notification $notification)
    {
        $this->notification = $notification;
        $this->tries = (int) config('notifications.max_attempts', 3);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        // Reload notification from database to get latest state
        $this->notification->refresh();

        // Mark as queued on first attempt
        if ($this->notification->attempts === 0) {
            $this->notification->markAsQueued();
        }

        try {
            // Send based on channel
            match ($this->notification->channel) {
                Notification::CHANNEL_EMAIL => $this->sendEmail(),
                Notification::CHANNEL_SMS => $this->sendSms(),
                Notification::CHANNEL_TELEGRAM => $this->sendTelegram(),
                Notification::CHANNEL_PUSH => $this->sendPush(),
                default => throw new Exception('Unsupported notification channel: ' . $this->notification->channel),
            };

            // Mark as sent on success
            $this->notification->markAsSent();

            Log::info('Notification sent successfully', [
                'notification_id' => $this->notification->id,
                'channel' => $this->notification->channel,
                'to' => $this->notification->to,
            ]);
        } catch (Exception $e) {
            // Increment attempts
            $this->notification->incrementAttempts();

            Log::error('Notification send failed', [
                'notification_id' => $this->notification->id,
                'channel' => $this->notification->channel,
                'error' => $e->getMessage(),
                'attempts' => $this->notification->attempts,
            ]);

            // Mark as failed if max attempts reached
            if ($this->notification->attempts >= $this->tries) {
                $this->notification->markAsFailed();

                Log::warning('Notification marked as failed after max attempts', [
                    'notification_id' => $this->notification->id,
                    'attempts' => $this->notification->attempts,
                ]);
            }

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Send notification via email.
     *
     * @return void
     * @throws Exception
     */
    protected function sendEmail(): void
    {
        if (empty($this->notification->to)) {
            throw new Exception('Email recipient address is required.');
        }

        Mail::to($this->notification->to)
            ->send(new GenericNotificationMail(
                $this->notification->subject ?? 'Notification',
                $this->notification->body,
                $this->notification->payload ?? []
            ));
    }

    /**
     * Send notification via SMS.
     *
     * @return void
     * @throws Exception
     */
    protected function sendSms(): void
    {
        if (empty($this->notification->to)) {
            throw new Exception('SMS recipient phone number is required.');
        }

        $smsService = app(SmsService::class);
        $smsService->send(
            $this->notification->to,
            $this->notification->body
        );
    }

    /**
     * Send notification via Telegram.
     *
     * @return void
     * @throws Exception
     */
    protected function sendTelegram(): void
    {
        if (empty($this->notification->to)) {
            throw new Exception('Telegram chat ID is required.');
        }

        $telegramService = app(TelegramService::class);
        $telegramService->send(
            $this->notification->to,
            $this->notification->body
        );
    }

    /**
     * Send push notification.
     *
     * @return void
     * @throws Exception
     */
    protected function sendPush(): void
    {
        // TODO: Implement push notification logic
        // This would typically integrate with FCM, APNS, or a service like OneSignal

        throw new Exception('Push notifications are not yet implemented.');
    }

    /**
     * Handle a job failure.
     *
     * @param Exception $exception
     * @return void
     */
    public function failed(Exception $exception): void
    {
        Log::critical('Notification job failed permanently', [
            'notification_id' => $this->notification->id,
            'channel' => $this->notification->channel,
            'error' => $exception->getMessage(),
            'attempts' => $this->notification->attempts,
        ]);

        // Ensure notification is marked as failed
        $this->notification->refresh();
        if (!$this->notification->isFailed()) {
            $this->notification->markAsFailed();
        }
    }
}
