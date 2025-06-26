<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\AIClientInterface;
use App\Contracts\ImapClientInterface;
use App\Contracts\MailerServiceInterface;
use App\Models\EmailReply;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final readonly class InboxController
{
    private AIClientInterface $aiClient;
    private MailerServiceInterface $mailerService;
    private ImapClientInterface $imapClient;

    public function __construct(
        AIClientInterface $aiClient, 
        MailerServiceInterface $mailerService,
        ImapClientInterface $imapClient
    ) {
        $this->aiClient = $aiClient;
        $this->mailerService = $mailerService;
        $this->imapClient = $imapClient;
    }

    /**
     * Display a listing of the inbox emails.
     */
    public function index(): Response
    {
        try {
            /** @var object|Collection<int, array{id: string, subject: string, from: string, to: string, date: \Carbon\Carbon, body: string, html: ?string, message_id: string}> $emails */
            $this->imapClient->getInboxEmails();

            // Log configuration status
            $imap_host = config('imap.accounts.default.host');
            $imap_username = config('imap.accounts.default.username');

            logger()->info('IMAP Config Check', [
                'host' => $imap_host,
                'username' => $imap_username,
                'client_class' => get_class($this->imapClient),
            ]);

            // Get emails with detailed logging
            logger()->info('Attempting to retrieve emails from IMAP server');
            $emails = $this->imapClient->getInboxEmails();

            // Log the emails that were retrieved
            logger()->info('Retrieved '.($emails instanceof Collection ? $emails->count() : count($emails)).' emails', [
                'sample_emails' => $emails instanceof Collection
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
            $isEmpty = $emails instanceof Collection ? $emails->isEmpty() : empty($emails);
            if ($isEmpty) {
                logger()->warning('No emails found in IMAP inbox');
            }

            // Ensure emails is a proper array for JSON serialization
            $emailsArray = $emails instanceof Collection ? $emails->toArray() : $emails;

            // Add detailed debugging to check the structure of each email
            foreach ($emailsArray as $index => $email) {
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

            return Inertia::render('Inbox/Index', [
                'emails' => $emailsArray,
            ]);
        } catch (Exception $e) {
            logger()->error('Error retrieving emails from IMAP server', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('Inbox/Index', [
                'emails' => [],
                'error' => 'Failed to connect to the email server: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Display the specified email.
     */
    public function show(Request $request, string $id): Response
    {
        logger()->info('Show email requested for ID: '.$id);

        try {
            $email = $this->imapClient->getEmail($id);

            if (! $email) {
                logger()->warning('Email not found with ID: '.$id);

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
    public function generateReply(Request $request, string $id): Response
    {
        $validated = $request->validate([
            'instruction' => ['required', 'string'],
        ]);

        $email = $this->imapClient->getEmail($id);

        if (! $email) {
            return Inertia::render('Inbox/NotFound');
        }

        // Get any existing chat history
        $reply = EmailReply::query()->where('email_id', $id)->first();
        $chatHistory = $reply?->chat_history ?? [];

        // Generate reply using AI
        $result = $this->aiClient->generateReply($email, $validated['instruction'], $chatHistory);

        // Save the reply and chat history
        $this->mailerService->saveDraftReply($id, $result['reply'], $result['chat_history']);

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
    public function sendReply(Request $request, string $id): Response
    {
        $validated = $request->validate([
            'reply' => ['required', 'string'],
        ]);

        $email = $this->imapClient->getEmail($id);

        if (! $email) {
            return Inertia::render('Inbox/NotFound');
        }

        EmailReply::updateOrCreate(
            ['email_id' => $id],
            ['latest_ai_reply' => $validated['reply'], 'sent_at' => now()]
        );

        return $this->mailerService->sendReply($email, $validated['reply'])
            ? Inertia::render('Inbox/Index', [
                'message' => 'Reply sent successfully',
                'success' => true,
            ])
            : Inertia::render('Inbox/Show', [
                'email' => $email,
                'latestReply' => $validated['reply'],
                'message' => 'Failed to send reply. Please try again.',
                'success' => false,
            ]);
    }
}
