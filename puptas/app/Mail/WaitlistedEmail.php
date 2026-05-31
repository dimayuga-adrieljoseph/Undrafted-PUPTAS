<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class WaitlistedEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $passer;
    public $messageTemplate;

    /**
     * Create a new message instance.
     */
    public function __construct($passer, $messageTemplate)
    {
        $this->passer = $passer;
        $this->messageTemplate = $messageTemplate;
        $this->onQueue('emails');
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $plainText = $this->convertHtmlToPlainText($this->messageTemplate);

        return $this->subject('PUP Taguig - Waitlist Status Update')
                    ->html($this->messageTemplate)
                    ->text('emails.plain.waitlisted', [
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
}
