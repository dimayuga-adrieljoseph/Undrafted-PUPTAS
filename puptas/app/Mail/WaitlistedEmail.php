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
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('PUP Taguig - Waitlist Status Update')
                    ->view('emails.waitlisted')
                    ->with([
                        'passerName' => trim($this->passer->first_name . ' ' . $this->passer->surname),
                        'firstName' => $this->passer->first_name,
                        'surname' => $this->passer->surname,
                        'referenceNumber' => $this->passer->reference_number,
                        'customMessage' => $this->messageTemplate,
                    ]);
    }
}
