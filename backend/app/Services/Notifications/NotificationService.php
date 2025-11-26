<?php

namespace App\Services\Notifications;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    /**
     * Send notification to user.
     */
    public function send(
        User $user,
        string $title,
        string $message,
        string $type = 'info',
        ?array $data = null,
        ?string $actionUrl = null
    ): Notification {
        return Notification::create([
            'user_id' => $user->id,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'data' => $data,
            'action_url' => $actionUrl,
        ]);
    }

    /**
     * Send transaction notification.
     */
    public function sendTransactionNotification(
        User $user,
        string $type,
        float $amount,
        string $currency,
        string $status
    ): Notification {
        $titles = [
            'deposit' => 'Deposit ' . ucfirst($status),
            'withdrawal' => 'Withdrawal ' . ucfirst($status),
            'transfer' => 'Transfer ' . ucfirst($status),
            'payment' => 'Payment ' . ucfirst($status),
        ];

        $messages = [
            'deposit' => "Your deposit of {$amount} {$currency} has been {$status}.",
            'withdrawal' => "Your withdrawal of {$amount} {$currency} has been {$status}.",
            'transfer' => "Your transfer of {$amount} {$currency} has been {$status}.",
            'payment' => "Your payment of {$amount} {$currency} has been {$status}.",
        ];

        return $this->send(
            $user,
            $titles[$type] ?? 'Transaction ' . ucfirst($status),
            $messages[$type] ?? "Transaction of {$amount} {$currency} has been {$status}.",
            'transaction',
            ['type' => $type, 'amount' => $amount, 'currency' => $currency, 'status' => $status]
        );
    }

    /**
     * Send KYC notification.
     */
    public function sendKycNotification(User $user, string $documentType, string $status): Notification
    {
        $statusMessages = [
            'verified' => 'Your KYC document has been verified.',
            'rejected' => 'Your KYC document has been rejected. Please resubmit.',
            'pending' => 'Your KYC document has been received and is under review.',
        ];

        return $this->send(
            $user,
            'KYC Update',
            $statusMessages[$status] ?? 'Your KYC status has been updated.',
            'kyc',
            ['document_type' => $documentType, 'status' => $status]
        );
    }

    /**
     * Send welcome notification.
     */
    public function sendWelcomeNotification(User $user): Notification
    {
        return $this->send(
            $user,
            'Welcome to MyXenPay!',
            'Your account has been created successfully. Complete your KYC to unlock all features.',
            'welcome',
            null,
            '/kyc'
        );
    }

    /**
     * Send security notification.
     */
    public function sendSecurityNotification(User $user, string $action): Notification
    {
        $messages = [
            'password_changed' => 'Your password has been changed.',
            'login' => 'New login detected on your account.',
            'wallet_linked' => 'A Solana wallet has been linked to your account.',
        ];

        return $this->send(
            $user,
            'Security Alert',
            $messages[$action] ?? 'A security-related action was performed on your account.',
            'security',
            ['action' => $action]
        );
    }

    /**
     * Get unread count for user.
     */
    public function getUnreadCount(User $user): int
    {
        return Notification::where('user_id', $user->id)->unread()->count();
    }

    /**
     * Mark all as read.
     */
    public function markAllAsRead(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->unread()
            ->update(['read_at' => now()]);
    }
}
