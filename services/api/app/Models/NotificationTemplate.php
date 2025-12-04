<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NotificationTemplate Model
 *
 * Represents a reusable template for generating notification content.
 * Templates support variable substitution using {{ variable }} syntax.
 *
 * @property int $id
 * @property string $name
 * @property string $event_type
 * @property string $channel
 * @property string|null $subject_template
 * @property string $body_template
 * @property bool $is_active
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class NotificationTemplate extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'event_type',
        'channel',
        'subject_template',
        'body_template',
        'is_active',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the user who created this template.
     *
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Render the subject template with provided variables.
     *
     * @param array $variables
     * @return string|null
     */
    public function renderSubject(array $variables = []): ?string
    {
        if (!$this->subject_template) {
            return null;
        }

        return $this->renderTemplate($this->subject_template, $variables);
    }

    /**
     * Render the body template with provided variables.
     *
     * @param array $variables
     * @return string
     */
    public function renderBody(array $variables = []): string
    {
        return $this->renderTemplate($this->body_template, $variables);
    }

    /**
     * Render a template string by replacing {{ variable }} placeholders.
     *
     * @param string $template
     * @param array $variables
     * @return string
     */
    protected function renderTemplate(string $template, array $variables): string
    {
        $rendered = $template;

        foreach ($variables as $key => $value) {
            // Replace {{ key }} with value
            $rendered = str_replace('{{ ' . $key . ' }}', $value, $rendered);
            // Also support {{key}} without spaces
            $rendered = str_replace('{{' . $key . '}}', $value, $rendered);
        }

        return $rendered;
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

    /**
     * Scope to filter only active templates.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Find an active template by event type and channel.
     *
     * @param string $eventType
     * @param string $channel
     * @return self|null
     */
    public static function findActiveTemplate(string $eventType, string $channel): ?self
    {
        return self::where('event_type', $eventType)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->first();
    }
}
