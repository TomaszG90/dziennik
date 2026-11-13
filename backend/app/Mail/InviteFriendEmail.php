<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InviteFriendEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $inviter,
        public readonly string $recipientEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Zaproszenie do Dziennika podróży',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invite-friend',
        );
    }
}
