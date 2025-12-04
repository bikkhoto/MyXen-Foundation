<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notification Model
 *
 * Represents a notification sent or to be sent to a user via various channels.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $event_type
 * @property string $channel
 * @property string $to
 * @property string|null $subject
 * @property string $body
 * @property array|null $payload
 * @property string $status
 * @property int $attempts
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Notification extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'event_type',
        'channel',
        'to',
        'subject',
        'body',
        'payload',
        'status',
        'attempts',
        'sent_at',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'payload' => 'array',
        'sent_at' => 'datetime',
        'attempts' => 'integer',
    ];

    /**
     * Status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    /**
     * Channel constants
     */
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_TELEGRAM = 'telegram';
    public const CHANNEL_PUSH = 'push';

    /**
     * Get the user associated with this notification.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who created this notification.
     *
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Mark the notification as queued.
     *
     * @return bool
     */
    public function markAsQueued(): bool
    {
        $this->status = self::STATUS_QUEUED;
        return $this->save();
    }

    /**
     * Mark the notification as sent.
     *
     * @return bool
     */
    public function markAsSent(): bool
    {
        $this->status = self::STATUS_SENT;
        $this->sent_at = now();
        return $this->save();
    }

    /**
     * Mark the notification as failed.
     *
     * @return bool
     */
    public function markAsFailed(): bool
    {
        $this->status = self::STATUS_FAILED;
        return $this->save();
    }

    /**
     * Increment the attempts counter.
     *
     * @return bool
     */
    public function incrementAttempts(): bool
    {
        $this->attempts++;
        return $this->save();
    }

    /**
     * Check if the notification is pending.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the notification is queued.
     *
     * @return bool
     */
    public function isQueued(): bool
    {
        return $this->status === self::STATUS_QUEUED;
    }

    /**
     * Check if the notification was sent.
     *
     * @return bool
     */
    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    /**
     * Check if the notification failed.
     *
     * @return bool
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Scope to filter by event type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $eventType
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope to filter by status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by channel.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $channel
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }
}
