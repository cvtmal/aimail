<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Interface for AI Client services
 */
interface AIClientInterface
{
    /**
     * Generate an AI reply based on an email and user instruction
     *
     * @param  array<string, mixed>  $email  The email data
     * @param  string  $userInstruction  The user's instruction
     * @return array{reply: string, chat_history: array<int, array{role: string, content: string}>}
     */
    public function generateReply(array $email, string $userInstruction): array;

    /**
     * Add a conversation exchange to the chat history
     *
     * @param  string  $emailId  The email ID
     * @param  string  $userInstruction  The user's instruction
     * @param  string  $aiReply  The AI's reply
     * @return bool Whether the operation succeeded
     */
    public function addToChatHistory(string $emailId, string $userInstruction, string $aiReply): bool;
}
