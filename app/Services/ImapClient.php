<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;
use Webklex\IMAP\Facades\Client;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Client as ImapClientLib;

final readonly class ImapClient
{
    /**
     * Connect to the IMAP server and get the client
     */
    public function getClient(): ImapClientLib
    {
        try {
            logger()->info('Getting IMAP client with config', [
                'host' => config('imap.accounts.default.host'),
                'port' => config('imap.accounts.default.port'),
                'protocol' => config('imap.accounts.default.protocol'),
                'encryption' => config('imap.accounts.default.encryption')
            ]);
            
            return Client::account('default');
        } catch (\Exception $e) {
            logger()->error('Failed to get IMAP client', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get all emails from the inbox
     * 
     * @return Collection<int, array{
     *  id: string,
     *  subject: string,
     *  from: string,
     *  date: \Carbon\Carbon,
     *  message_id: string,
     * }>
     */
    public function getInboxEmails(): Collection
    {
        try {
            logger()->info('Connecting to IMAP server');
            $client = $this->getClient();
            
            logger()->info('Attempting to connect to IMAP server');
            $client->connect();
            logger()->info('Connected to IMAP server successfully');
            
            logger()->info('Getting INBOX folder');
            $folder = $client->getFolder('INBOX');
            
            logger()->info('Querying messages from INBOX');
            $messages = $folder->query()->all()->get();
            logger()->info('Retrieved ' . $messages->count() . ' messages from INBOX');
            
            if ($messages->isEmpty()) {
                logger()->warning('No messages found in INBOX');
            }
            
            return $messages->map(function (Message $message) {
                // Get date and convert it to a string format that works with JavaScript
                $dateAttribute = $message->getDate();
                $dateString = null;
                if ($dateAttribute && method_exists($dateAttribute, 'first')) {
                    // Get DateTime object from the attribute
                    $dateValue = $dateAttribute->first();
                    if ($dateValue instanceof \DateTime) {
                        $dateString = $dateValue->format('c'); // ISO 8601 date
                    } elseif ($dateValue) {
                        $dateString = (string)$dateValue;
                    }
                }
                
                return [
                    'id' => $message->getUid(),
                    'subject' => $message->getSubject() ?? 'No Subject',
                    'from' => $message->getFrom()[0]->mail ?? 'Unknown',
                    'date' => $dateString,
                    'message_id' => $message->getMessageId(),
                ];
            });
        } catch (\Exception $e) {
            logger()->error('Error in getInboxEmails', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get a specific email by its ID
     * 
     * @param string $id The email UID
     * @return array{
     *  id: string,
     *  subject: string,
     *  from: string,
     *  to: string,
     *  date: \Carbon\Carbon,
     *  body: string,
     *  html: ?string,
     *  message_id: string,
     * }|null
     */
    public function getEmail(string $messageId): ?array
    {
        try {
            logger()->info('Getting email with UID: ' . $messageId);
            
            $client = $this->getClient();
            logger()->info('Connecting to IMAP server for email retrieval');
            $client->connect();
            
            logger()->info('Getting INBOX folder');
            $folder = $client->getFolder('INBOX');
            
            logger()->info('Querying for message by UID: ' . $messageId);
            
            // The correct way to get a message by UID with webklex/php-imap
            try {
                // First attempt - try direct UID fetch
                $message = null;
                
                // Get message by UID
                $messages = $folder->messages()
                    ->setFetchBody(true)
                    ->setFetchFlags(true)
                    ->uid($messageId)
                    ->get();
                
                if ($messages && count($messages) > 0) {
                    $message = $messages->first();
                }
                
                // If that fails, try with a query
                if (!$message) {
                    logger()->info('UID direct fetch failed, trying query');
                    $messages = $folder->query()
                        ->setFetchBody(true)
                        ->setFetchFlags(true)
                        ->whereUid($messageId)
                        ->get();
                        
                    if ($messages && count($messages) > 0) {
                        $message = $messages->first();
                    }
                }
                
                // Last resort: get all and filter manually
                if (!$message) {
                    logger()->info('Uid not found with direct methods, fetching recent emails and filtering');
                    $allMessages = $folder->messages()->limit(20)->get();
                    $filteredMessages = $allMessages->filter(function ($msg) use ($messageId) {
                        return (string)$msg->getUid() === (string)$messageId;
                    });
                    
                    if ($filteredMessages && count($filteredMessages) > 0) {
                        $message = $filteredMessages->first();
                    }
                }
            } catch (\Exception $e) {
                logger()->error('Error while querying email by UID: ' . $e->getMessage());
                // Don't fall back to most recent message - let's be explicit about failures
                return null;
            }
            
            if (!$message) {
                logger()->warning('No message found with ID: ' . $messageId);
                return null;
            }
        
            // Format date in the same way as getInboxEmails
            $dateAttribute = $message->getDate();
            $dateString = null;
            if ($dateAttribute && method_exists($dateAttribute, 'first')) {
                $dateValue = $dateAttribute->first();
                if ($dateValue instanceof \DateTime) {
                    $dateString = $dateValue->format('c'); // ISO 8601 date
                } elseif ($dateValue) {
                    $dateString = (string)$dateValue;
                }
            }
            
            // Convert subject to string if it's an Attribute object
            $subject = $message->getSubject();
            if (is_object($subject) && method_exists($subject, 'toString')) {
                $subject = $subject->toString();
            } elseif (is_object($subject)) {
                $subject = (string)$subject;
            }
            $subject = $subject ?: 'No Subject';
            
            // Convert message_id to string if it's an Attribute object
            $messageIdValue = $message->getMessageId();
            if (is_object($messageIdValue) && method_exists($messageIdValue, 'toString')) {
                $messageIdValue = $messageIdValue->toString();
            } elseif (is_object($messageIdValue)) {
                $messageIdValue = (string)$messageIdValue;
            }
            
            logger()->info('Successfully retrieved email details', [
                'message_id' => $messageId,
                'subject' => $subject
            ]);
            
            // Handle from and to addresses, ensuring they're strings
            $from = $message->getFrom();
            $fromAddress = 'Unknown';
            if ($from && !empty($from) && isset($from[0]->mail)) {
                $fromAddress = $from[0]->mail;
            }
            
            $to = $message->getTo();
            $toAddress = 'Unknown';
            if ($to && !empty($to) && isset($to[0]->mail)) {
                $toAddress = $to[0]->mail;
            }
            
            return [
                'id' => (string)$message->getUid(),
                'subject' => $subject,
                'from' => $fromAddress,
                'to' => $toAddress,
                'date' => $dateString,
                'body' => (string)$message->getTextBody(),
                'html' => $message->getHtmlBody() ? (string)$message->getHtmlBody() : null,
                'message_id' => $messageIdValue,
            ];
        } catch (\Exception $e) {
            logger()->error('Error retrieving email with ID: ' . $messageId, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}
