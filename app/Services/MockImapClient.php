<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Webklex\PHPIMAP\Message;

/**
 * Mock implementation of ImapClient for development/testing
 * This allows development without a real IMAP connection
 */
final readonly class MockImapClient
{
    /**
     * Get a list of emails in the inbox
     *
     * @return array<array{
     *  id: string,
     *  subject: string,
     *  from: string,
     *  to: string,
     *  date: \Carbon\Carbon,
     *  body: string,
     *  html: ?string,
     *  message_id: string,
     * }> List of emails
     */
    public function getInboxEmails(): array
    {
        return [
            [
                'id' => 'email-001',
                'subject' => 'Project Update: Q3 Goals',
                'from' => 'jane.manager@example.com',
                'to' => 'user@example.com',
                'date' => Carbon::now()->subDays(1),
                'body' => "Hi team,\n\nI wanted to provide an update on our Q3 goals. We're tracking well on most metrics, but I'd like to schedule a review meeting next week.\n\nCould you please share your availability?\n\nBest,\nJane",
                'html' => "<div>Hi team,<br><br>I wanted to provide an update on our Q3 goals. We're tracking well on most metrics, but I'd like to schedule a review meeting next week.<br><br>Could you please share your availability?<br><br>Best,<br>Jane</div>",
                'message_id' => '<project-update-123@example.com>',
            ],
            [
                'id' => 'email-002',
                'subject' => 'Client Meeting - Follow-up',
                'from' => 'robert.client@acme.com',
                'to' => 'user@example.com',
                'date' => Carbon::now()->subDays(2),
                'body' => "Hello,\n\nThank you for meeting with us yesterday. The presentation was well-received, and the team is excited about the potential collaboration.\n\nI have a few questions about the timeline and budget. Could we schedule a quick call this week?\n\nRegards,\nRobert",
                'html' => '<div>Hello,<br><br>Thank you for meeting with us yesterday. The presentation was well-received, and the team is excited about the potential collaboration.<br><br>I have a few questions about the timeline and budget. Could we schedule a quick call this week?<br><br>Regards,<br>Robert</div>',
                'message_id' => '<client-meeting-456@acme.com>',
            ],
            [
                'id' => 'email-003',
                'subject' => 'Conference Speaking Opportunity',
                'from' => 'events@techconf.org',
                'to' => 'user@example.com',
                'date' => Carbon::now()->subDays(5),
                'body' => "Dear Speaker,\n\nWe're pleased to invite you to speak at our upcoming TechConf 2023. Based on your expertise in AI and machine learning, we think you'd be perfect for a 30-minute session on recent developments.\n\nPlease let me know if you're interested, and I can share more details.\n\nSincerely,\nConference Organizing Team",
                'html' => "<div>Dear Speaker,<br><br>We're pleased to invite you to speak at our upcoming TechConf 2023. Based on your expertise in AI and machine learning, we think you'd be perfect for a 30-minute session on recent developments.<br><br>Please let me know if you're interested, and I can share more details.<br><br>Sincerely,<br>Conference Organizing Team</div>",
                'message_id' => '<invite-789@techconf.org>',
            ],
            [
                'id' => 'email-004',
                'subject' => 'Your Subscription Renewal',
                'from' => 'billing@saasproduct.com',
                'to' => 'user@example.com',
                'date' => Carbon::now()->subHours(12),
                'body' => "Hello,\n\nYour subscription to SaasProduct Pro is set to renew on July 15th. Your card ending in 4567 will be charged $99.99.\n\nIf you'd like to make any changes to your subscription, please visit your account settings or contact our support team.\n\nThank you for being a valued customer!\n\nThe SaasProduct Team",
                'html' => "<div>Hello,<br><br>Your subscription to SaasProduct Pro is set to renew on July 15th. Your card ending in 4567 will be charged $99.99.<br><br>If you'd like to make any changes to your subscription, please visit your account settings or contact our support team.<br><br>Thank you for being a valued customer!<br><br>The SaasProduct Team</div>",
                'message_id' => '<billing-246@saasproduct.com>',
            ],
            [
                'id' => 'email-005',
                'subject' => 'Feedback on Your Recent Pull Request',
                'from' => 'dev.lead@company.com',
                'to' => 'user@example.com',
                'date' => Carbon::now()->subHours(6),
                'body' => "Hi Developer,\n\nI've reviewed your PR for the authentication feature. Overall, it looks good, but I have a few suggestions:\n\n1. Consider adding more comprehensive tests for edge cases\n2. The password reset flow needs a clearer error message\n3. Let's discuss the rate limiting approach\n\nLet me know when you'd like to chat about these points.\n\nRegards,\nDev Lead",
                'html' => "<div>Hi Developer,<br><br>I've reviewed your PR for the authentication feature. Overall, it looks good, but I have a few suggestions:<br><br>1. Consider adding more comprehensive tests for edge cases<br>2. The password reset flow needs a clearer error message<br>3. Let's discuss the rate limiting approach<br><br>Let me know when you'd like to chat about these points.<br><br>Regards,<br>Dev Lead</div>",
                'message_id' => '<code-review-357@company.com>',
            ],
        ];
    }

    /**
     * Get a specific email by ID
     *
     * @param  string  $emailId  Email ID
     * @return ?array{
     *  id: string,
     *  subject: string,
     *  from: string,
     *  to: string,
     *  date: \Carbon\Carbon,
     *  body: string,
     *  html: ?string,
     *  message_id: string,
     * } Email data or null if not found
     */
    public function getEmail(string $emailId): ?array
    {
        $emails = $this->getInboxEmails();

        foreach ($emails as $email) {
            if ($email['id'] === $emailId) {
                return $email;
            }
        }

        return null;
    }
}
