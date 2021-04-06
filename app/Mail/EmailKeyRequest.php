<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailKeyRequest extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    protected $message;

    public function __construct($subject, $message)
    {
        $this->subject = $subject;
        $this->message = $message;
    }

    public function build()
    {
        return $this->view('emails.key_request')
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->subject($this->subject)
            ->with(['content' => $this->message]);
    }
}
