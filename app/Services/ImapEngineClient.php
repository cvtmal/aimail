<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ImapClientInterface;
use DirectoryTree\ImapEngine\Enums\ImapFetchIdentifier;
use DirectoryTree\ImapEngine\Mailbox;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final readonly class ImapEngineClient implements ImapClientInterface
{
    /**
     * Get all emails from the inbox
     *
     * @param  string|null  $account  The account identifier
     * @return Collection<int, array{
     *  id: string,
     *  subject: string,
     *  from: string,
     *  date: string,
     *  message_id: string,
     * }>
     */
    public function getInboxEmails(?string $account = null): Collection
    {
        try {
            $accountId = $account ?? config('imapengine.default', 'default');
            Log::info('Connecting to ImapEngine server', ['account' => $accountId]);

            // Get the mailbox
            $mailbox = Mailbox::make(config("imapengine.mailboxes.{$accountId}"));

            // Access the inbox
            $inbox = $mailbox->inbox();

            // Get messages from the inbox
            $messages = $inbox->messages()->withHeaders()->withBody()->get();

            Log::info('Retrieved '.count($messages).' messages from INBOX with ImapEngine', [
                'account' => $accountId,
            ]);

            if (count($messages) === 0) {
                Log::warning('No messages found in INBOX using ImapEngine');

                return collect([]);
            }

            return collect($messages)->map(function ($message) {
                try {
                    // Format the message data for consistency with the webklex implementation
                    $subject = $message->subject() ?? 'No Subject';

                    // Extract the from address
                    $fromAddress = $this->extractEmailAddress($message->from());

                    // Get date string
                    $date = $message->date();
                    $dateString = $date ? $date->format('c') : date('c');

                    return [
                        'id' => $message->uid(),
                        'subject' => $subject,
                        'from' => $fromAddress,
                        'date' => $dateString,
                        'message_id' => $message->messageId() ?? '',
                    ];
                } catch (Exception $e) {
                    Log::error('Error processing ImapEngine message', [
                        'error' => $e->getMessage(),
                    ]);

                    return [
                        'id' => $message->uid() ?? 'error',
                        'subject' => 'Error: Unable to process email',
                        'from' => 'Unknown',
                        'date' => date('c'),
                        'message_id' => '',
                    ];
                }
            });
        } catch (Exception $e) {
            Log::error('Error in ImapEngine getInboxEmails', [
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
     *  date: string,
     *  body: string,
     *  html: ?string,
     *  message_id: string,
     * }|null
     */
    public function getEmail(string $messageId, ?string $account = null): ?array
    {
        try {
            $accountId = $account ?? config('imapengine.default', 'default');
            Log::info('Attempting to retrieve email with ID: '.$messageId, ['account' => $accountId]);

            // Get the mailbox
            $mailbox = Mailbox::make(config("imapengine.mailboxes.{$accountId}"));

            // Access the inbox
            $inbox = $mailbox->inbox();

            // Attempt to retrieve the message directly by UID
            try {
                $message = $inbox->messages()
                    ->withHeaders()
                    ->withBody()
                    ->find((int) $messageId, ImapFetchIdentifier::Uid);
            } catch (Exception $e) {
                Log::warning('Direct UID lookup failed with ImapEngine: '.$e->getMessage(), ['account' => $accountId]);
                $message = null;
            }

            // If still not found, try searching all messages
            if (! $message) {
                Log::info('UID search failed, trying to scan all messages', ['account' => $accountId]);
                try {
                    $allMessages = $inbox->messages()->withHeaders()->withBody()->get();

                    foreach ($allMessages as $msg) {
                        if ((string) $msg->uid() === $messageId) {
                            $message = $msg;
                            break;
                        }
                    }
                } catch (Exception $e) {
                    Log::error('Error scanning all messages with ImapEngine: '.$e->getMessage(), ['account' => $accountId]);
                }
            }

            if (! $message) {
                Log::warning('No message found with ID: '.$messageId);

                return null;
            }

            // Format the subject
            $subject = $message->subject() ?? 'No Subject';

            // Extract the from and to addresses
            $fromAddress = $this->extractEmailAddress($message->from());

            $toAddress = $this->extractEmailAddress($message->to());

            // Get date string
            $date = $message->date();
            $dateString = $date ? $date->format('c') : date('c');

            // Get message body
            $textBody = $message->text() ?? '';
            $htmlBody = $message->html() ?? null;

            Log::info('Successfully retrieved email details with ImapEngine', [
                'message_id' => $messageId,
                'subject' => $subject,
            ]);

            return [
                'id' => $message->uid(),
                'subject' => $subject,
                'from' => $fromAddress,
                'to' => $toAddress,
                'date' => $dateString,
                'body' => $textBody,
                'html' => $htmlBody,
                'message_id' => $message->messageId() ?? '',
            ];
        } catch (Exception $e) {
            Log::error('Error retrieving email with ID: '.$messageId, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Attempt to extract a single email address from the various address
     * representations returned by ImapEngine. Falls back to "Unknown" when
     * no valid address can be resolved.
     */
    private function extractEmailAddress(mixed $address): string
    {
        if (is_string($address) && $address !== '') {
            return $address;
        }

        if (is_object($address)) {
            // Prefer explicit accessor
            if (method_exists($address, 'email')) {
                return (string) $address->email();
            }
            // Attempt string cast and parse
            if (method_exists($address, '__toString')) {
                $asString = (string) $address;
                $parsed = $this->parseEmailFromString($asString);
                if ($parsed !== null) {
                    return $parsed;
                }
            }
        }

        // Iterable set of addresses – grab the first resolvable
        if (is_iterable($address)) {
            foreach ($address as $item) {
                $resolved = $this->extractEmailAddress($item);
                if ($resolved !== 'Unknown') {
                    return $resolved;
                }
            }
        }

        return 'Unknown';
    }

    /**
     * Attempt to parse an email address from a string like
     * "Name <email@example.com>".
     */
    private function parseEmailFromString(string $value): ?string
    {
        // Already plain email?
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return $value;
        }
        if (preg_match('/<([^>]+)>/', $value, $m)) {
            $candidate = mb_trim($m[1]);
            if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }
        if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $value, $m)) {
            return $m[0];
        }

        return null;
    }
}
