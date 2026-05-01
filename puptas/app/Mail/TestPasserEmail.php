<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Part\TextPart;

class TestPasserEmail extends Mailable implements ShouldQueue, ShouldBeUnique
{
    use Queueable, SerializesModels;

    public $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return (string) $this->passer->test_passer_id;
    }

    /**
     * Create a new message instance.
     */



    public $passer;
    public $messageTemplate;

    public function __construct($passer, $messageTemplate)
    {
        $this->passer = $passer;
        $this->messageTemplate = $messageTemplate;
    }

    public function build()
    {
        return $this->subject('PUPCET Results')
                    ->html($this->messageTemplate);  // Pass plain HTML string here
    }

    /**
     * Get the message envelope.
     */
    // public function envelope(): Envelope
    // {
    //     return new Envelope(
    //         subject: 'Test Passer Email',
    //     );
    // }

    /**
     * Get the message content definition.
     */
    // public function content(): Content
    // {
    //     return new Content(
    //         view: 'view.name',
    //     );
    // }

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
