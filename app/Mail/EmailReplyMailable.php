<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class EmailReplyMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance
     */
    public function __construct(
        private readonly string $replyContent,
        private readonly string $emailSubject,
        private readonly string $recipientEmail,
        private readonly string $originalMessageId,
    ) {
        $this->subject($emailSubject);
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this
            ->to($this->recipientEmail)
            ->replyTo(config('mail.from.address'), config('mail.from.name'))
            ->text('emails.reply-plain', ['content' => $this->replyContent])
            ->withSymfonyMessage(function ($message) {
                $message->getHeaders()
                    ->addTextHeader('References', $this->originalMessageId)
                    ->addTextHeader('In-Reply-To', $this->originalMessageId);
            });
    }
}
