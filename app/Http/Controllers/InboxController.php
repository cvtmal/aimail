<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EmailReply;
use App\Services\AIClient;
use App\Services\ImapClient;
use App\Services\MailerService;
use App\Services\MockImapClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;

final class InboxController
{
    private ImapClient|MockImapClient $imapClient;
    private AIClient $aiClient;
    private MailerService $mailerService;
    private bool $useMockClient;

    public function __construct(AIClient $aiClient, MailerService $mailerService)
    {
        $this->aiClient = $aiClient;
        $this->mailerService = $mailerService;
        
        // Check if IMAP credentials are explicitly set in config
        $hasImapCredentials = !empty(config('imap.accounts.default.username')) && !empty(config('imap.accounts.default.host'));
        
        // Only use mock client if credentials are definitely not set
        $this->useMockClient = App::environment('testing') || !$hasImapCredentials;
        
        if ($this->useMockClient) {
            $this->imapClient = new MockImapClient();
        } else {
            $this->imapClient = app(ImapClient::class);
        }
    }
    
    /**
     * Display a listing of emails.
     */
    public function index(): Response
    {
        try {
            // Log configuration status
            $imap_host = config('imap.accounts.default.host');
            $imap_username = config('imap.accounts.default.username');
            $using_mock = $this->useMockClient;
            
            logger()->info('IMAP Config Check', [
                'host' => $imap_host,
                'username' => $imap_username,
                'using_mock_client' => $using_mock,
                'client_class' => get_class($this->imapClient)
            ]);
            
            // Get emails with detailed logging
            logger()->info('Attempting to retrieve emails from IMAP server');
            $emails = $this->imapClient->getInboxEmails();
            
            // Log the emails that were retrieved
            logger()->info('Retrieved ' . $emails->count() . ' emails', [
                'sample_emails' => $emails->take(3)->map(function($email) {
                    return [
                        'subject' => $email['subject'] ?? 'No Subject',
                        'from' => $email['from'] ?? 'Unknown',
                        'has_date' => isset($email['date']) ? 'yes' : 'no',
                        'date_type' => isset($email['date']) ? gettype($email['date']) : 'undefined'
                    ];
                })->toArray()
            ]);
            
            if ($emails->isEmpty() && !$this->useMockClient) {
                // If we have real credentials but no emails, try using mock client as fallback
                logger()->warning('No emails found with real IMAP client, falling back to mock data');
                $mockClient = new MockImapClient();
                $emails = $mockClient->getInboxEmails();
                
                logger()->info('Retrieved ' . $emails->count() . ' mock emails');
            }
            
            // Ensure emails is a proper array for JSON serialization
            $emailsArray = $emails->toArray();
            
            // Add detailed debugging to check the structure of each email
            foreach ($emailsArray as $index => $email) {
                logger()->info("Email data structure for email #{$index}", [
                    'id_type' => gettype($email['id']),
                    'subject_type' => gettype($email['subject']),
                    'from_type' => gettype($email['from']),
                    'date_type' => gettype($email['date']),
                    'message_id_type' => gettype($email['message_id']),
                    'id' => $email['id'],
                    'date' => $email['date'],
                ]);
                
                // Ensure all values are strings or primitive types
                foreach ($email as $key => $value) {
                    if (is_object($value) || (is_array($value) && !empty($value))) {
                        logger()->error("Non-primitive value found in email data", [
                            'key' => $key,
                            'value_type' => gettype($value),
                            'value' => is_object($value) ? get_class($value) : json_encode($value)
                        ]);
                        
                        // Convert to string to prevent React errors
                        $emailsArray[$index][$key] = is_object($value) ? '[Object]' : json_encode($value);
                    }
                }
            }
            
            logger()->info('Preparing to render Inbox/Index with ' . count($emailsArray) . ' emails');
            
            return Inertia::render('Inbox/Index', [
                'emails' => $emailsArray,
                'usingMockData' => $this->useMockClient
            ]);
        } catch (\Exception $e) {
            logger()->error('Error retrieving emails: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Fallback to mock data if there's an error
            $mockClient = new MockImapClient();
            $emails = $mockClient->getInboxEmails();
            $emailsArray = $emails->toArray();
            
            // Apply the same type safety check for mock data
            foreach ($emailsArray as $index => $email) {
                foreach ($email as $key => $value) {
                    if (is_object($value) || (is_array($value) && !empty($value))) {
                        logger()->error("Non-primitive value found in mock email data", [
                            'key' => $key,
                            'value_type' => gettype($value)
                        ]);
                        
                        // Convert to string to prevent React errors
                        $emailsArray[$index][$key] = is_object($value) ? '[Object]' : json_encode($value);
                    }
                }
            }
            
            logger()->error('Using mock data due to error', [
                'error' => $e->getMessage(),
                'mock_emails_count' => count($emailsArray)
            ]);
            
            return Inertia::render('Inbox/Index', [
                'emails' => $emailsArray,
                'error' => 'Failed to connect to email server: ' . $e->getMessage(),
                'usingMockData' => true
            ]);
        }
    }
    
    /**
     * Display the specified email.
     */
    public function show(string $id): Response
    {
        logger()->info('Show email requested for ID: ' . $id);
        
        try {
            $email = $this->imapClient->getEmail($id);
            
            if (!$email) {
                logger()->warning('Email not found with ID: ' . $id);
                return Inertia::render('Inbox/NotFound');
            }
            
            logger()->info('Email found and retrieved', ['subject' => $email['subject'] ?? 'No Subject']);
            
            // Apply the same type safety check as in the index method
            foreach ($email as $key => $value) {
                if (is_object($value) || (is_array($value) && !empty($value) && !is_string($value))) {
                    logger()->error("Non-primitive value found in email detail data", [
                        'key' => $key,
                        'value_type' => gettype($value),
                    ]);
                    
                    // Convert to string to prevent React errors
                    $email[$key] = is_object($value) ? '[Object]' : json_encode($value);
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
        } catch (\Exception $e) {
            logger()->error('Error retrieving email details', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Inertia::render('Inbox/NotFound', [
                'error' => 'Failed to retrieve email: ' . $e->getMessage()
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
        
        if (!$email) {
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
        
        if (!$email) {
            return Inertia::render('Inbox/NotFound');
        }
        
        // Send the reply
        $success = $this->mailerService->sendReply($email, $validated['reply']);
        
        return Inertia::render('Inbox/Show', [
            'email' => $email,
            'latestReply' => $validated['reply'],
            'message' => $success ? 'Reply sent successfully.' : 'Failed to send reply. Please try again.',
            'success' => $success,
        ]);
    }
}
