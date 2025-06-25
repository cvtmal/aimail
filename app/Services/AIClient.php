<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final readonly class AIClient
{
    private string $apiKey;

    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.ai.key');
        $this->apiUrl = config('services.ai.url');
    }

    /**
     * Generate a reply for an email using the AI
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
     * } $email The email data
     * @param  string  $instruction  User's instruction for the AI
     * @param  array<int, array{role: string, content: string}>  $chatHistory  Previous chat history
     * @return array{
     *  reply: string,
     *  chat_history: array<int, array{role: string, content: string}>
     * }
     */
    public function generateReply(array $email, string $instruction, array $chatHistory = []): array
    {
        // Prepare the system message that explains the AI's task
        $systemMessage = [
            'role' => 'system',
            'content' => "You are an email assistant that helps the user craft replies. The user will provide you with an email to respond to and specific instructions on how to craft the reply. Generate a professional and appropriate response according to the user's instructions.",
        ];

        // Prepare the initial message containing the email content
        $emailContextMessage = [
            'role' => 'user',
            'content' => "I need to reply to this email:\n\nFrom: {$email['from']}\nSubject: {$email['subject']}\nDate: {$email['date']}\n\n{$email['body']}",
        ];

        // Prepare the instruction message
        $instructionMessage = [
            'role' => 'user',
            'content' => $instruction,
        ];

        // Construct the messages array for the AI
        $messages = [$systemMessage];

        // If we have chat history, add it after the system message
        if (! empty($chatHistory)) {
            array_push($messages, ...$chatHistory);
        } else {
            // If this is the first interaction, add the email context
            $messages[] = $emailContextMessage;
        }

        // Add the latest instruction
        $messages[] = $instructionMessage;

        // Call the AI API
        $response = $this->getClient()->post($this->apiUrl, [
            'model' => 'gpt-4',
            'messages' => $messages,
            'temperature' => 0.7,
        ]);

        if ($response->failed()) {
            throw new Exception("Failed to generate reply: {$response->body()}");
        }

        $data = $response->json();
        $reply = $data['choices'][0]['message']['content'] ?? '';

        // Update the chat history with the new messages and AI response
        if (empty($chatHistory)) {
            $chatHistory[] = $emailContextMessage;
        }

        $chatHistory[] = $instructionMessage;
        $chatHistory[] = [
            'role' => 'assistant',
            'content' => $reply,
        ];

        return [
            'reply' => $reply,
            'chat_history' => $chatHistory,
        ];
    }

    /**
     * Add a user instruction and AI reply to the chat history for an email
     *
     * @param  string  $emailId  The email ID to add chat history for
     * @param  string  $userInstruction  The user's instruction to the AI
     * @param  string  $aiReply  The AI's reply
     * @return bool Whether the chat history was successfully saved
     */
    public function addToChatHistory(string $emailId, string $userInstruction, string $aiReply): bool
    {
        try {
            $existingReply = \App\Models\EmailReply::firstOrNew(['email_id' => $emailId]);

            // Initialize chat history if it doesn't exist
            $chatHistory = $existingReply->chat_history ?? [];

            // If this is the first interaction, add the system message
            if (empty($chatHistory)) {
                $chatHistory[] = [
                    'role' => 'system',
                    'content' => 'You are a helpful email assistant.',
                ];
            }

            // Add user instruction
            $chatHistory[] = [
                'role' => 'user',
                'content' => $userInstruction,
            ];

            // Add AI reply
            $chatHistory[] = [
                'role' => 'assistant',
                'content' => $aiReply,
            ];

            // Update the model
            $existingReply->chat_history = $chatHistory;
            $existingReply->latest_ai_reply = $aiReply;
            $existingReply->save();

            return true;
        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to add chat history', [
                'email_id' => $emailId,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get the HTTP client with proper authentication headers
     */
    private function getClient(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ]);
    }
}
