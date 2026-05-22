<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CongratulationsMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $recipientEmail;

    /**
     * Create a new message instance.
     */
    public function __construct(string $email = '')
    {
        $this->recipientEmail = $email;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Congratulations! PUP Taguig Admission')
                    ->view('emails.congratulations');
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
