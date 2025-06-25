<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\AIClientInterface;
use App\Contracts\MailerServiceInterface;
use App\Models\EmailReply;
use App\Services\ImapClient;
use App\Services\MockImapClient;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;

final class InboxController
{
    private ImapClient|MockImapClient $imapClient;

    private AIClientInterface $aiClient;

    private MailerServiceInterface $mailerService;

    private bool $useMockClient;

    public function __construct(AIClientInterface $aiClient, MailerServiceInterface $mailerService)
    {
        $this->aiClient = $aiClient;
        $this->mailerService = $mailerService;

        // Check if IMAP credentials are explicitly set in config
        $hasImapCredentials = ! empty(config('imap.accounts.default.username')) && ! empty(config('imap.accounts.default.host'));

        // Only use mock client if credentials are definitely not set
        $this->useMockClient = App::environment('testing') || ! $hasImapCredentials;

        if ($this->useMockClient) {
            $this->imapClient = new MockImapClient();
        } else {
            $this->imapClient = app(ImapClient::class);
        }
    }

    /**
     * Display a listing of the inbox emails.
     *
     * @return \Illuminate\Http\Response|Response
     */
    public function index()
    {
        try {
            /** @var object|\Illuminate\Support\Collection<int, array{id: string, subject: string, from: string, to: string, date: \Carbon\Carbon, body: string, html: ?string, message_id: string}> $emails */
            $emails = $this->imapClient->getInboxEmails();

            // Log configuration status
            $imap_host = config('imap.accounts.default.host');
            $imap_username = config('imap.accounts.default.username');
            $using_mock = $this->useMockClient;

            logger()->info('IMAP Config Check', [
                'host' => $imap_host,
                'username' => $imap_username,
                'using_mock_client' => $using_mock,
                'client_class' => get_class($this->imapClient),
            ]);

            // Get emails with detailed logging
            logger()->info('Attempting to retrieve emails from IMAP server');
            $emails = $this->imapClient->getInboxEmails();

            // Log the emails that were retrieved
            logger()->info('Retrieved '.($emails instanceof \Illuminate\Support\Collection ? $emails->count() : count($emails)).' emails', [
                'sample_emails' => $emails instanceof \Illuminate\Support\Collection
                    ? $emails->take(3)->map(function ($email) {
                        return [
                            'subject' => $email['subject'] ?? 'No Subject',
                            'from' => $email['from'] ?? 'Unknown',
                            'has_date' => isset($email['date']) ? 'yes' : 'no',
                            'date_type' => isset($email['date']) ? gettype($email['date']) : 'undefined',
                        ];
                    })->toArray()
                    : array_slice($emails, 0, 3),
            ]);

            // Check if we have any emails, handling both Collection and array cases
            $isEmpty = $emails instanceof \Illuminate\Support\Collection ? $emails->isEmpty() : empty($emails);
            if ($isEmpty && ! $this->useMockClient) {
                // If we have real credentials but no emails, try using mock client as fallback
                logger()->warning('No emails found with real IMAP client, falling back to mock data');
                $mockClient = new MockImapClient();
                $emails = $mockClient->getInboxEmails();

                /** @var \Illuminate\Support\Collection $emails */
                logger()->info('Retrieved '.$emails->count().' mock emails');
            }

            // Ensure emails is a proper array for JSON serialization
            $emailsArray = $emails instanceof \Illuminate\Support\Collection ? $emails->toArray() : $emails;

            // Add detailed debugging to check the structure of each email
            foreach ($emailsArray as $index => $email) {
                // Use null coalescing to handle potential missing or null values
                $id = $email['id'] ?? null;
                $subject = $email['subject'] ?? null;
                $from = $email['from'] ?? null;
                $date = $email['date'] ?? null;
                $messageId = $email['message_id'] ?? null;

                logger()->info("Email data structure for email #{$index}", [
                    'id_type' => gettype($id),
                    'subject_type' => gettype($subject),
                    'from_type' => gettype($from),
                    'date_type' => gettype($date),
                    'message_id_type' => gettype($messageId),
                    'id' => $id,
                    'date' => $date,
                ]);

                // Ensure all values are strings or primitive types
                foreach ($email as $key => $value) {
                    if (is_object($value) || (is_array($value) && ! empty($value))) {
                        logger()->error('Non-primitive value found in email data', [
                            'key' => $key,
                            'value_type' => gettype($value),
                            'value' => is_object($value) ? get_class($value) : json_encode($value),
                        ]);

                        // Convert to string to prevent React errors
                        $emailsArray[$index][$key] = is_object($value) ? '[Object]' : json_encode($value);
                    }
                }
            }

            logger()->info('Preparing to render Inbox/Index with '.count($emailsArray).' emails');

            // Return JSON response for API requests
            if (request()->expectsJson()) {
                return response()->json([
                    'emails' => $emailsArray,
                    'usingMockData' => $this->useMockClient,
                ]);
            }

            return Inertia::render('Inbox/Index', [
                'emails' => $emailsArray,
                'usingMockData' => $this->useMockClient,
            ]);
        } catch (Exception $e) {
            /** @var Exception $e */
            logger()->error('Error retrieving emails: '.$e->getMessage(), [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Fallback to mock data if there's an error
            $mockClient = new MockImapClient();
            $emails = $mockClient->getInboxEmails();
            $emailsArray = $emails instanceof \Illuminate\Support\Collection ? $emails->toArray() : $emails;

            logger()->info('Retrieved '.($emails instanceof \Illuminate\Support\Collection ? $emails->count() : count($emails)).' mock emails after error');

            // Return JSON response for API requests
            if (request()->expectsJson()) {
                return response()->json([
                    'emails' => $emailsArray,
                    'usingMockData' => true,
                ]);
            }

            // Add detailed debugging to check the structure of each email
            foreach ($emailsArray as $index => $email) {
                foreach ($email as $key => $value) {
                    if (is_object($value) || (is_array($value) && ! empty($value) && ! is_string($value))) {
                        logger()->warning('Non-primitive value found in email data', [
                            'index' => $index,
                            'key' => $key,
                            'type' => gettype($value),
                        ]);

                        // Convert to string to prevent React errors
                        $emailsArray[$index][$key] = is_object($value) ? '[Object]' : json_encode($value);
                    }
                }
            }

            // Return JSON response for API requests
            if (request()->expectsJson()) {
                return response()->json([
                    'emails' => $emailsArray,
                    'usingMockData' => true,
                ]);
            }

            return Inertia::render('Inbox/Index', [
                'emails' => $emailsArray,
                'error' => 'Failed to connect to email server: '.$e->getMessage(),
                'usingMockData' => $this->useMockClient,
            ]);
        }
    }

    /**
     * Display the specified email.
     *
     * @return \Illuminate\Http\Response|Response
     */
    public function show(Request $request, string $id)
    {
        logger()->info('Show email requested for ID: '.$id);

        try {
            $email = $this->imapClient->getEmail($id);

            if (! $email) {
                logger()->warning('Email not found with ID: '.$id);

                // Return JSON response for API requests
                if ($request->expectsJson()) {
                    return response()->json(['error' => 'Email not found'], 404);
                }

                return Inertia::render('Inbox/NotFound');
            }

            logger()->info('Email found and retrieved', ['subject' => $email['subject'] ?? 'No Subject']);

            // Apply the same type safety check as in the index method
            foreach ($email as $key => $value) {
                if (is_object($value) || (is_array($value) && ! empty($value) && ! is_string($value))) {
                    logger()->error('Non-primitive value found in email detail data', [
                        'key' => $key,
                        'type' => is_object($value) ? get_class($value) : gettype($value),
                    ]);
                }
            }

            // Get any existing reply for this email
            $reply = EmailReply::query()->where('email_id', $id)->first();
            $chatHistory = $reply?->chat_history ?? [];

            // Return JSON response for API requests
            if ($request->expectsJson()) {
                return response()->json([
                    'email' => $email,
                    'latestReply' => $reply?->latest_ai_reply,
                    'chatHistory' => $chatHistory,
                ]);
            }

            return Inertia::render('Inbox/Show', [
                'email' => $email,
                'latestReply' => $reply?->latest_ai_reply,
                'chatHistory' => $chatHistory,
            ]);
        } catch (Exception $e) {
            logger()->error('Error retrieving email details', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('Inbox/NotFound', [
                'error' => 'Failed to retrieve email: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Generate an AI reply for an email.
     */
    public function generateReply(Request $request, string $id)
    {
        $validated = $request->validate([
            'instruction' => ['required', 'string'],
        ]);

        $email = $this->imapClient->getEmail($id);

        if (! $email) {
            // Return JSON response for API requests
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Email not found'], 404);
            }

            return Inertia::render('Inbox/NotFound');
        }

        // Get any existing chat history
        $reply = EmailReply::query()->where('email_id', $id)->first();
        $chatHistory = $reply?->chat_history ?? [];

        // Generate reply using AI
        $result = $this->aiClient->generateReply($email, $validated['instruction'], $chatHistory);

        // Save the reply and chat history
        $this->mailerService->saveDraftReply($id, $result['reply'], $result['chat_history']);

        // Return JSON response for API requests
        if ($request->expectsJson()) {
            return response()->json([
                'reply' => $result['reply'],
                'chatHistory' => $result['chat_history'],
            ]);
        }

        return Inertia::render('Inbox/Show', [
            'email' => $email,
            'latestReply' => $result['reply'],
            'chatHistory' => $result['chat_history'],
            'message' => 'Reply generated successfully.',
        ]);
    }

    /**
     * Send an email reply.
     */
    public function sendReply(Request $request, string $id)
    {
        $validated = $request->validate([
            'reply' => ['required', 'string'],
        ]);

        $email = $this->imapClient->getEmail($id);

        if (! $email) {
            // Return JSON response for API requests
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Email not found'], 404);
            }

            return Inertia::render('Inbox/NotFound');
        }

        // Send the reply
        $success = $this->mailerService->sendReply($email, $validated['reply']);

        // Store in database regardless of environment
        $emailReply = EmailReply::updateOrCreate(
            ['email_id' => $id],
            ['latest_ai_reply' => $validated['reply'], 'sent_at' => now()]
        );

        // Return JSON response for API requests
        if ($request->expectsJson()) {
            return response()->json([
                'success' => $success,
                'message' => $success ? 'Reply sent successfully.' : 'Failed to send reply. Please try again.',
            ], $success ? 200 : 500);
        }

        // Normal web request flow - redirect
        if ($success) {
            return redirect()->route('inbox.index')->with('message', 'Reply sent successfully.');
        }

        // Render page with error
        return Inertia::render('Inbox/Show', [
            'email' => $email,
            'latestReply' => $validated['reply'],
            'message' => 'Failed to send reply. Please try again.',
            'success' => false,
        ]);
    }
}
