<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\EmailReply;

/**
 * Interface for Mailer Service
 */
interface MailerServiceInterface
{
    /**
     * Send a reply to an email
     *
     * @param  array<string, mixed>  $email  The email data
     * @param  string  $replyContent  The content of the reply
     * @param  string|null  $account  The account identifier to send from
     * @return bool Whether the email was sent successfully
     */
    public function sendReply(array $email, string $replyContent, ?string $account = null): bool;

    /**
     * Save a draft reply for an email
     *
     * @param  string  $emailId  The ID of the email
     * @param  string  $replyContent  The content of the reply
     * @param  array<int, array<string, string>>  $chatHistory  The chat history
     * @param  string|null  $account  The account identifier
     * @return EmailReply The saved email reply model
     */
    public function saveDraftReply(string $emailId, string $replyContent, array $chatHistory, ?string $account = null): EmailReply;
}
