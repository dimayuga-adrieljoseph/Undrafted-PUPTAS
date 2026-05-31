<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Part\TextPart;

class TestPasserEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */



    public $passer;
    public $messageTemplate;

    public function __construct($passer, $messageTemplate)
    {
        $this->passer = $passer;
        $this->messageTemplate = $messageTemplate;
        $this->onQueue('emails');
    }

    public function build()
    {
        $plainText = $this->convertHtmlToPlainText($this->messageTemplate);

        return $this->subject('PUPCET Results')
                    ->html($this->messageTemplate)
                    ->text('emails.plain.congratulations', [
                        'plainTextContent' => $plainText,
                    ]);
    }

    /**
     * Convert HTML email content to a readable plain text version.
     */
    private function convertHtmlToPlainText(string $html): string
    {
        // Replace <br> and block-level closing tags with newlines
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $text = preg_replace('/<\/(p|div|h[1-6]|li|tr)>/i', "\n", $text);
        $text = preg_replace('/<(p|div|h[1-6])[^>]*>/i', "\n", $text);

        // Convert links to "text (url)" format
        $text = preg_replace('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i', '$2 ($1)', $text);

        // Convert list items
        $text = preg_replace('/<li[^>]*>/i', '• ', $text);

        // Strip remaining HTML tags
        $text = strip_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Normalize whitespace: collapse multiple blank lines
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
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
