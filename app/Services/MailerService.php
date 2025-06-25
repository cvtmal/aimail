<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\MailerServiceInterface;
use App\Mail\EmailReplyMailable;
use App\Models\EmailReply;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class MailerService implements MailerServiceInterface
{
    /**
     * Send a reply to an email
     *
     * @param array{
     *  id: string,
     *  subject: string,
     *  from: string,
     *  to: string,
     *  date: \Carbon\Carbon,
     *  body: string,
     *  html: ?string,
     *  message_id: string,
     * } $email The original email data
     * @param  string  $replyContent  The content of the reply
     * @return bool Whether the email was sent successfully
     */
    public function sendReply(array $email, string $replyContent): bool
    {
        $subject = $this->formatReplySubject($email['subject']);

        try {
            Mail::to($email['from'])->send(new EmailReplyMailable(
                replyContent: $replyContent,
                emailSubject: $subject,
                recipientEmail: $email['from'],
                originalMessageId: $email['message_id']
            ));

            // Store the reply in the database
            EmailReply::query()->create([
                'email_id' => $email['id'],
                'latest_ai_reply' => $replyContent,
                'sent_at' => now(),
                // Chat history will be passed separately if needed
                'chat_history' => [],
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to send email reply: '.$e->getMessage(), [
                'email_id' => $email['id'],
                'exception' => $e,
            ]);

            return false;
        }
    }

    /**
     * Save a draft reply without sending it
     *
     * @param  string  $emailId  The email ID
     * @param  string  $replyContent  The draft reply content
     * @param  array<int, array{role: string, content: string}>  $chatHistory  The chat history
     */
    public function saveDraftReply(string $emailId, string $replyContent, array $chatHistory): EmailReply
    {
        return EmailReply::query()->updateOrCreate(
            ['email_id' => $emailId],
            [
                'latest_ai_reply' => $replyContent,
                'chat_history' => $chatHistory,
                // sent_at remains null for drafts
            ]
        );
    }

    /**
     * Format the reply subject to include Re: if not already present
     *
     * @param  string  $originalSubject  The original email subject
     * @return string Formatted subject
     */
    private function formatReplySubject(string $originalSubject): string
    {
        if (str_starts_with(mb_strtolower($originalSubject), 're:')) {
            return $originalSubject;
        }

        return "Re: $originalSubject";
    }
}
