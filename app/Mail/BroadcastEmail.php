<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * BroadcastEmail
 * ─────────────────────────────────────────────────────────────
 * Generic mailable for admin-composed broadcasts. Subject + body
 * come from the admin form. Body supports plain newlines (rendered
 * via nl2br in the template) — keeping the format simple keeps the
 * UI simple. Rich-text upgrade is a future iteration.
 *
 * Recipient name is interpolated into the greeting via the template
 * so each user gets a personalised "Hi {name}" without us having to
 * pre-render bodies per recipient.
 */
class BroadcastEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $subjectLine;
    public string $bodyText;
    public string $recipientName;

    public function __construct(string $subjectLine, string $bodyText, string $recipientName)
    {
        $this->subjectLine   = $subjectLine;
        $this->bodyText      = $bodyText;
        $this->recipientName = $recipientName;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.broadcast',
            with: [
                'subjectLine'   => $this->subjectLine,
                'bodyText'      => $this->bodyText,
                'recipientName' => $this->recipientName,
            ],
        );
    }
}