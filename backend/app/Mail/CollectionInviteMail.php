<?php

namespace App\Mail;

use App\Models\CollectionInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CollectionInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly string $registrationUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public readonly CollectionInvite $invite,
        string $plaintextToken,
    ) {
        $this->registrationUrl = sprintf(
            '%s/register?token=%s&email=%s',
            rtrim(config('frontend.url'), '/'),
            $plaintextToken,
            urlencode($invite->email),
        );
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('Einladung zu "%s" auf BasixMeeple', $this->invite->collection->name),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.collection-invite',
            with: [
                'collectionName' => $this->invite->collection->name,
                'inviterName' => $this->invite->invitedBy->name,
                'registrationUrl' => $this->registrationUrl,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
