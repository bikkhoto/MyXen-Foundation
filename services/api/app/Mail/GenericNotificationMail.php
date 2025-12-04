<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Generic Notification Mail
 *
 * A flexible mailable for sending notification emails with dynamic content.
 */
class GenericNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The email subject.
     *
     * @var string
     */
    public string $emailSubject;

    /**
     * The email body content.
     *
     * @var string
     */
    public string $emailBody;

    /**
     * Additional payload data.
     *
     * @var array
     */
    public array $payload;

    /**
     * Create a new message instance.
     *
     * @param string $subject
     * @param string $body
     * @param array $payload
     */
    public function __construct(string $subject, string $body, array $payload = [])
    {
        $this->emailSubject = $subject;
        $this->emailBody = $body;
        $this->payload = $payload;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.generic-notification',
            with: [
                'body' => $this->emailBody,
                'payload' => $this->payload,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

