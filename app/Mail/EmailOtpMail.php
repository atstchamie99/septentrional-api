<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EmailOtpMail extends Mailable
{
    public function __construct(public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('no-reply@septentrional.tg', 'Septentrional'),
            subject: 'Votre code de connexion — Septentrional',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.otp');
    }
}
