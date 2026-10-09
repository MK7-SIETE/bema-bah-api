<?php

namespace App\Mail;

use App\Models\Newsletter;
use App\Models\Subscriber;
use App\Models\SiteConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Newsletter  $newsletter,
        public Subscriber  $subscriber,
    ) {}

    public function envelope(): Envelope
    {
        $dealership = SiteConfig::get('dealership_name', 'Mukuba Motors');
        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(
                config('mail.from.address'),
                $dealership
            ),
            subject: $this->newsletter->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
