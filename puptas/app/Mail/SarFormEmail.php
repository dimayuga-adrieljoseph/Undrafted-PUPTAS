<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SarFormEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $passer;
    public $downloadUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($passer, $downloadUrl)
    {
        $this->passer = $passer;
        $this->downloadUrl = $downloadUrl;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $fullName = trim($this->passer->surname . ', ' . $this->passer->first_name . ' ' . ($this->passer->middle_name ?? ''));
        
        return $this->subject('PUP Taguig - Student Admission Record (SAR)')
                    ->view('emails.sar-form')
                    ->with([
                        'passerName' => $fullName,
                        'referenceNumber' => $this->passer->reference_number,
                        'downloadUrl' => $this->downloadUrl,
                    ]);
    }
}
