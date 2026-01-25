<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GeneralMail extends Mailable
{
    use Queueable, SerializesModels;

    public $recipientName;
    public $subject;
    public $messageContent;
    public $recipientType;
    public $emailType;

    public function __construct($recipientName, $subject, $messageContent, $recipientType, $emailType)
    {
        $this->recipientName = $recipientName;
        $this->subject = $subject;
        $this->messageContent = $messageContent;
        $this->recipientType = $recipientType;
        $this->emailType = $emailType;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.general',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}