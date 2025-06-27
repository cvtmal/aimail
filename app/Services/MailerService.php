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
     * @param  string|null  $account  The account identifier to send from
     * @return bool Whether the email was sent successfully
     */
    public function sendReply(array $email, string $replyContent, ?string $account = null): bool
    {
        $subject = $this->formatReplySubject($email['subject']);
        $accountId = $account ?? config('mail.default');
        $mailerKey = $this->resolveMailerKey((string) $accountId);

        // Convert combined plain text (reply + optional signature) to safe HTML
        $replyHtml = nl2br(e(mb_rtrim($replyContent)));

        try {
            $mailer = Mail::mailer($mailerKey);

            $mailer->to($email['from'])->send(new EmailReplyMailable(
                replyContent: $replyHtml,
                emailSubject: $subject,
                recipientEmail: $email['from'],
                originalMessageId: $email['message_id'],
                account: $accountId
            ));

            EmailReply::query()->create([
                'email_id' => $email['id'],
                'latest_ai_reply' => $replyContent,
                'sent_at' => now(),
                'chat_history' => [],
                'account' => $accountId,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to send email reply: '.$e->getMessage(), [
                'email_id' => $email['id'],
                'account' => $accountId,
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
     * @param  string|null  $account  The account identifier
     */
    public function saveDraftReply(string $emailId, string $replyContent, array $chatHistory, ?string $account = null): EmailReply
    {
        $accountId = $account ?? config('mail.default');

        return EmailReply::query()->updateOrCreate(
            ['email_id' => $emailId],
            [
                'latest_ai_reply' => $replyContent,
                'chat_history' => $chatHistory,
                'account' => $accountId,
                // sent_at remains null for drafts
            ]
        );
    }

    /**
     * Resolve the Laravel mailer key for a given logical account identifier.
     *
     * This maps friendly account names used elsewhere in the code (e.g. "info",
     * "damian", or the default account id) to the mailer keys that are
     * configured in config/mail.php (e.g. "smtp1", "smtp2", etc.). If no
     * mapping exists we fall back to the primary "smtp" mailer.
     */
    private function resolveMailerKey(string $accountId): string
    {
        return match ($accountId) {
            'info' => 'smtp1',
            'damian' => 'smtp2',
            default => 'smtp',
        };
    }

    /**
     * Append the account-specific signature to the reply content if missing.
     *
     * @param  string  $replyContent  The AI-generated reply without signature
     * @param  string  $accountId  The logical account identifier (e.g. "info", "damian")
     * @return string Reply content with signature appended (if not already present)
     */
    private function appendSignature(string $replyContent, string $accountId): string
    {
        $signature = config('signatures.'.$accountId) ?? config('signatures.default', '');
        $signature = mb_trim($signature);

        // Convert the plain-text reply to HTML (escape + nl2br)
        $replyHtml = nl2br(e(mb_rtrim($replyContent)));

        // Avoid appending if signature already present
        if ($signature === '' || str_contains($replyHtml, $signature)) {
            return $replyHtml;
        }

        // Separate reply and signature with a <br><br>
        return $replyHtml.'<br><br>'.$signature;
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
