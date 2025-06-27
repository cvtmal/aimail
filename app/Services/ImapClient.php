<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ImapClientInterface;
use DateTimeImmutable;
use Exception;
use Illuminate\Support\Collection;
use Webklex\IMAP\Facades\Client;
use Webklex\PHPIMAP\Client as ImapClientLib;
use Webklex\PHPIMAP\Message;

final readonly class ImapClient implements ImapClientInterface
{
    /**
     * Connect to the IMAP server and get the client
     *
     * @param string|null $account The account identifier
     * @return ImapClientLib
     * @throws Exception
     */
    public function getClient(?string $account = null): ImapClientLib
    {
        try {
            $accountId = $account ?? config('imap.default', 'default');
            
            logger()->info('Getting IMAP client with config', [
                'account' => $accountId,
                'host' => config("imap.accounts.{$accountId}.host"),
                'port' => config("imap.accounts.{$accountId}.port"),
                'protocol' => config("imap.accounts.{$accountId}.protocol"),
                'encryption' => config("imap.accounts.{$accountId}.encryption"),
            ]);

            return Client::account($accountId);
        } catch (Exception $e) {
            logger()->error('Failed to get IMAP client', [
                'account' => $account ?? 'default',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get all emails from the inbox
     *
     * @param string|null $account The account identifier
     * @return Collection<int, array{
     *  id: string,
     *  subject: string,
     *  from: string,
     *  date: \Carbon\Carbon,
     *  message_id: string,
     * }>
     */
    public function getInboxEmails(?string $account = null): Collection
    {
        try {
            $accountId = $account ?? config('imap.default', 'default');
            logger()->info('Connecting to IMAP server', ['account' => $accountId]);
            $client = $this->getClient($accountId);

            logger()->info('Attempting to connect to IMAP server');
            $client->connect();
            logger()->info('Connected to IMAP server successfully');

            logger()->info('Getting INBOX folder');
            $folder = $client->getFolder('INBOX');
            
            // Get account-specific options
            $options = config("imap.accounts.{$accountId}.options", []);
            $limit = $options['limit'] ?? 100;
            $fetchOrder = $options['fetch_order'] ?? 'desc'; // Default to newest first
            
            logger()->info('Setting up memory-optimized IMAP query', [
                'account' => $accountId,
                'limit' => $limit,
                'order' => $fetchOrder
            ]);
            
            try {
                // Create a memory-optimized query
                $query = $folder->query();
                
                // Don't fetch message bodies to save memory
                $query->setFetchBody(false);
                
                // Don't mark messages as read when fetching them
                $query->leaveUnread();
                      
                if ($fetchOrder === 'desc') {
                    // Sort by date descending (newest first)
                    $query->setFetchOrderDesc();
                } else {
                    // Sort by date ascending (oldest first)
                    $query->setFetchOrderAsc();
                }
                
                logger()->info('Executing memory-optimized IMAP query');
                
                // Fetch only limited number of messages
                $messages = $query->all()->limit($limit)->get();
                
                if ($messages->isEmpty()) {
                    // Fallback approach if no messages found
                    logger()->warning('Optimized query returned no results, trying alternative approach');
                    
                    // Create a simpler query directly from the folder
                    $messages = $folder->messages()
                        ->setFetchBody(false) // Don't fetch message bodies
                        ->leaveUnread()       // Don't mark as read
                        ->limit($limit)       // Limit results
                        ->get();              // Get messages
                    
                    logger()->info('Alternative approach retrieved ' . $messages->count() . ' messages');
                }
            } catch (\Exception $e) {
                // If we encounter any error, log it and return an empty collection
                logger()->error('Error processing IMAP query', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                return collect([]);
            }
            
            logger()->info('Retrieved ' . ($messages->count()) . ' messages from INBOX', [
                'account' => $accountId
            ]);

            if ($messages->isEmpty()) {
                logger()->warning('No messages found in INBOX');
                return collect([]);
            }

            return $messages->map(function (Message $message) {
                try {
                    // Get date and convert it to a string format
                    $dateAttribute = $message->getDate();
                    $dateString = null;
                    if ($dateAttribute && method_exists($dateAttribute, 'first')) {
                        // Get DateTime object from the attribute
                        $dateValue = $dateAttribute->first();
                        if ($dateValue instanceof DateTimeImmutable) {
                            $dateString = $dateValue->format('c'); // ISO 8601 date
                        } elseif ($dateValue) {
                            $dateString = (string) $dateValue;
                        }
                    }

                    // Convert subject to string
                    $subject = $message->getSubject();
                    if (is_object($subject) && method_exists($subject, 'toString')) {
                        $subject = $subject->toString();
                    } elseif (is_object($subject)) {
                        $subject = (string) $subject;
                    }
                    $subject = $subject ?: 'No Subject';

                    // Convert message_id to string
                    $messageIdValue = $message->getMessageId();
                    if (is_object($messageIdValue) && method_exists($messageIdValue, 'toString')) {
                        $messageIdValue = $messageIdValue->toString();
                    } elseif (is_object($messageIdValue)) {
                        $messageIdValue = (string) $messageIdValue;
                    }

                    // Handle from address
                    $from = $message->getFrom();
                    $fromAddress = 'Unknown';
                    if ($from && ! empty($from) && isset($from[0]->mail)) {
                        $fromAddress = $from[0]->mail;
                    }

                    // Return only the necessary metadata for inbox listing
                    return [
                        'id' => (string) $message->getUid(),
                        'subject' => $subject,
                        'from' => $fromAddress,
                        'date' => $dateString,
                        'message_id' => $messageIdValue,
                    ];
                } catch (\Exception $e) {
                    logger()->error('Error processing message in getInboxEmails', [
                        'error' => $e->getMessage(),
                        'uid' => $message->getUid() ?? 'unknown'
                    ]);
                    
                    // Return a minimal record for emails we can't process
                    return [
                        'id' => (string) ($message->getUid() ?? 'error'),
                        'subject' => 'Error: Unable to process email',
                        'from' => 'Unknown',
                        'date' => date('c'),
                        'message_id' => '',
                    ];
                }
            });
        } catch (Exception $e) {
            logger()->error('Error in getInboxEmails', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return collect([]);
        }
    }

    /**
     * Get a specific email by its ID
     *
     * @param  string  $messageId  The email UID
     * @param  string|null  $account  The account identifier
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
    public function getEmail(string $messageId, ?string $account = null): ?array
    {
        try {
            $accountId = $account ?? config('imap.default', 'default');
            logger()->info('Attempting to retrieve email with ID: '.$messageId, ['account' => $accountId]);
            
            // Normalize the message ID to ensure it's a valid UID string
            $normalizedId = trim((string)$messageId);
            logger()->info('Normalized message ID: '.$normalizedId);
            
            $client = $this->getClient($accountId);
            logger()->info('Connecting to IMAP server for email retrieval');
            $client->connect();

            logger()->info('Getting INBOX folder');
            $folder = $client->getFolder('INBOX');

            logger()->info('Querying for message by UID: '.$normalizedId, ['account' => $accountId]);

            // The correct way to get a message by UID with webklex/php-imap
            try {
                // First attempt - get message directly by UID using the proper API method
                $message = null;
                
                // Check if we can get available message IDs first
                try {
                    // Get a sample of messages to check their UIDs
                    $messages = $folder->query()
                        ->setFetchBody(false)
                        ->limit(3)
                        ->get();
                    
                    if ($messages && count($messages) > 0) {
                        $sampleUids = [];
                        foreach ($messages as $msg) {
                            $sampleUids[] = (string)$msg->getUid();
                        }
                        logger()->info('Sample UIDs from mailbox', [
                            'sample_uids' => $sampleUids,
                            'account' => $accountId
                        ]);
                    }
                } catch (Exception $e) {
                    logger()->warning('Error checking sample UIDs: ' . $e->getMessage());
                }
                
                logger()->info('Using getMessageByUid to fetch single email');
                try {
                    // According to php-imap.com docs, this is the proper way to fetch by UID
                    $query = $folder->query();
                    $message = $query->getMessageByUid($normalizedId);
                } catch (Exception $queryError) {
                    logger()->warning('Error in getMessageByUid: ' . $queryError->getMessage(), [
                        'account' => $accountId,
                        'uid' => $normalizedId
                    ]);
                    $message = null;
                }
                
                // If direct fetch failed, try searching messages
                if (!$message) {
                    logger()->info('UID fetch with getMessageByUid failed, trying all() method', ['account' => $accountId]);
                    try {
                        // Try with all() first as it's syntactically correct
                        $messages = $folder->query()->all()->get();
                        
                        // Find the message with matching UID
                        foreach ($messages as $msg) {
                            $uid = (string)$msg->getUid();
                            if ($uid === $normalizedId) {
                                $message = $msg;
                                logger()->info('Found message with matching UID', [
                                    'uid' => $uid,
                                    'account' => $accountId
                                ]);  
                                break;
                            }
                        }
                    } catch (Exception $queryError) {
                        logger()->warning('Error in messages all() fetch: ' . $queryError->getMessage(), ['account' => $accountId]);
                    }
                }

                // Last resort: get most recent emails and search manually (avoid UID SEARCH command)
                if (! $message) {
                    logger()->info('UID not found with direct methods, fetching all recent emails', ['account' => $accountId]);
                    try {
                        // Get most recent messages without any UID-based filters
                        $allMessages = $folder->messages()
                            ->setFetchBody(true)  // We need the body for individual email view
                            ->limit(100)          // Increase limit to improve chances of finding the email
                            ->get();
                            
                        logger()->info('Got ' . count($allMessages) . ' messages for manual filtering', ['account' => $accountId]);
                        
                        // Manually search through messages to find the one with matching UID
                        foreach ($allMessages as $msg) {
                            $uid = (string)$msg->getUid();
                            if ($uid === $normalizedId) {
                                logger()->info('Found exact match for message ID: ' . $normalizedId, ['account' => $accountId]);
                                $message = $msg;
                                break;
                            }
                        }
                        
                        // If still not found, try using a numerical search as a last resort
                        if (!$message && is_numeric($normalizedId)) {
                            logger()->info('Trying numerical search as last resort', ['account' => $accountId]);
                            foreach ($allMessages as $index => $msg) {
                                // Try matching by index position in the folder
                                if ((string)($index + 1) === $normalizedId) {
                                    logger()->info('Found message by numerical index: ' . $normalizedId, ['account' => $accountId]);
                                    $message = $msg;
                                    break;
                                }
                            }
                        }
                    } catch (Exception $fallbackError) {
                        logger()->error('Error in fallback email retrieval: ' . $fallbackError->getMessage(), ['account' => $accountId]);
                    }
                }
            } catch (Exception $e) {
                logger()->error('Error while querying email by UID: '.$e->getMessage());

                // Don't fall back to most recent message - let's be explicit about failures
                return null;
            }

            if (! $message) {
                logger()->warning('No message found with ID: '.$messageId);

                return null;
            }

            // Format date in the same way as getInboxEmails
            $dateAttribute = $message->getDate();
            $dateString = null;
            if ($dateAttribute && method_exists($dateAttribute, 'first')) {
                $dateValue = $dateAttribute->first();
                if ($dateValue instanceof DateTimeImmutable) {
                    $dateString = $dateValue->format('c'); // ISO 8601 date
                } elseif ($dateValue) {
                    $dateString = (string) $dateValue;
                }
            }

            // Convert subject to string if it's an Attribute object
            $subject = $message->getSubject();
            if (is_object($subject) && method_exists($subject, 'toString')) {
                $subject = $subject->toString();
            } elseif (is_object($subject)) {
                $subject = (string) $subject;
            }
            $subject = $subject ?: 'No Subject';

            // Convert message_id to string if it's an Attribute object
            $messageIdValue = $message->getMessageId();
            if (is_object($messageIdValue) && method_exists($messageIdValue, 'toString')) {
                $messageIdValue = $messageIdValue->toString();
            } elseif (is_object($messageIdValue)) {
                $messageIdValue = (string) $messageIdValue;
            }

            logger()->info('Successfully retrieved email details', [
                'message_id' => $messageId,
                'subject' => $subject,
            ]);

            // Handle from and to addresses, ensuring they're strings
            $from = $message->getFrom();
            $fromAddress = 'Unknown';
            if ($from && ! empty($from) && isset($from[0]->mail)) {
                $fromAddress = $from[0]->mail;
            }

            $to = $message->getTo();
            $toAddress = 'Unknown';
            if ($to && ! empty($to) && isset($to[0]->mail)) {
                $toAddress = $to[0]->mail;
            }

            return [
                'id' => (string) $message->getUid(),
                'subject' => $subject,
                'from' => $fromAddress,
                'to' => $toAddress,
                'date' => $dateString,
                'body' => (string) $message->getTextBody(),
                'html' => $message->getHtmlBody() ? (string) $message->getHtmlBody() : null,
                'message_id' => $messageIdValue,
            ];
        } catch (Exception $e) {
            logger()->error('Error retrieving email with ID: '.$messageId, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
}
