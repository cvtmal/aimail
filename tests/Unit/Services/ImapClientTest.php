<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ImapClient;
use Mockery;
use Tests\TestCase;
use Webklex\IMAP\Facades\Client;
use Webklex\PHPIMAP\Client as ImapClientLib;
use Webklex\PHPIMAP\Support\MessageCollection;

final class ImapClientTest extends TestCase
{
    public function test_get_inbox_emails_with_default_account(): void
    {
        // Mock the IMAP client, folder, and query
        $mockClient = Mockery::mock(ImapClientLib::class);
        $mockFolder = Mockery::mock('Webklex\PHPIMAP\Folder');
        $mockQuery = Mockery::mock('Webklex\PHPIMAP\Query\WhereQuery');
        $mockMessage = Mockery::mock('Webklex\PHPIMAP\Message');
        $mockMessageCollection = Mockery::mock(MessageCollection::class);

        // Set up expectations for default account
        Client::shouldReceive('account')->with('default')->once()->andReturn($mockClient);
        $mockClient->shouldReceive('connect')->once()->andReturnSelf();
        $mockClient->shouldReceive('getFolder')->with('INBOX')->once()->andReturn($mockFolder);

        // Mock query chain
        $mockFolder->shouldReceive('query')->once()->andReturn($mockQuery);
        $mockQuery->shouldReceive('all')->once()->andReturnSelf();
        $mockQuery->shouldReceive('get')->once()->andReturn($mockMessageCollection);

        // Mock collection behavior
        $mockMessageCollection->shouldReceive('isEmpty')->andReturn(false);
        $mockMessageCollection->shouldReceive('count')->andReturn(1);
        $mockMessageCollection->shouldReceive('map')->andReturn($mockMessageCollection);
        $mockMessageCollection->shouldReceive('toArray')->andReturn([$mockMessage]);

        // Set up message expectations
        $mockMessage->shouldReceive('getAttributes')->andReturn([
            'uid' => [123],
            'subject' => ['Test Subject'],
            'from' => ['test@example.com'],
            'date' => ['2025-06-26 12:00:00'],
        ]);

        // Create ImapClient instance and call getInboxEmails
        $imapClient = new ImapClient();
        $emails = $imapClient->getInboxEmails();

        // Assert result
        $this->assertCount(1, $emails);
        $this->assertEquals('123', $emails[0]['id']);
        $this->assertEquals('Test Subject', $emails[0]['subject']);
    }

    public function test_get_inbox_emails_with_specific_account(): void
    {
        // Mock the IMAP client, folder, and query
        $mockClient = Mockery::mock(ImapClientLib::class);
        $mockFolder = Mockery::mock('Webklex\PHPIMAP\Folder');
        $mockQuery = Mockery::mock('Webklex\PHPIMAP\Query\WhereQuery');
        $mockMessage = Mockery::mock('Webklex\PHPIMAP\Message');
        $mockMessageCollection = Mockery::mock(MessageCollection::class);

        // Set up expectations for work account
        Client::shouldReceive('account')->with('smtp1')->once()->andReturn($mockClient);
        $mockClient->shouldReceive('connect')->once()->andReturnSelf();
        $mockClient->shouldReceive('getFolder')->with('INBOX')->once()->andReturn($mockFolder);

        // Mock query chain
        $mockFolder->shouldReceive('query')->once()->andReturn($mockQuery);
        $mockQuery->shouldReceive('all')->once()->andReturnSelf();
        $mockQuery->shouldReceive('get')->once()->andReturn($mockMessageCollection);

        // Mock collection behavior
        $mockMessageCollection->shouldReceive('isEmpty')->andReturn(false);
        $mockMessageCollection->shouldReceive('count')->andReturn(1);
        $mockMessageCollection->shouldReceive('map')->andReturn($mockMessageCollection);
        $mockMessageCollection->shouldReceive('toArray')->andReturn([$mockMessage]);

        // Set up message expectations
        $mockMessage->shouldReceive('getAttributes')->andReturn([
            'uid' => [456],
            'subject' => ['Work Email Subject'],
            'from' => ['colleague@work.com'],
            'date' => ['2025-06-26 13:00:00'],
        ]);

        // Create ImapClient instance and call getInboxEmails with work account
        $imapClient = new ImapClient();
        $emails = $imapClient->getInboxEmails('smtp1');

        // Assert result
        $this->assertCount(1, $emails);
        $this->assertEquals('456', $emails[0]['id']);
        $this->assertEquals('Work Email Subject', $emails[0]['subject']);
    }

    public function test_get_email_with_specific_account(): void
    {
        // Mock the IMAP client, folder, and query
        $mockClient = Mockery::mock(ImapClientLib::class);
        $mockFolder = Mockery::mock('Webklex\PHPIMAP\Folder');
        $mockMessageQuery = Mockery::mock('Webklex\PHPIMAP\Query\WhereQuery');
        $mockMessage = Mockery::mock('Webklex\PHPIMAP\Message');
        $mockMessageCollection = Mockery::mock(MessageCollection::class);

        // Set up expectations for personal account
        Client::shouldReceive('account')->with('smtp2')->once()->andReturn($mockClient);
        $mockClient->shouldReceive('connect')->once()->andReturnSelf();
        $mockClient->shouldReceive('getFolder')->with('INBOX')->once()->andReturn($mockFolder);

        // Mock messages query chain
        $mockFolder->shouldReceive('messages')->once()->andReturn($mockMessageQuery);
        $mockMessageQuery->shouldReceive('setFetchBody')->with(true)->once()->andReturnSelf();
        $mockMessageQuery->shouldReceive('setFetchFlags')->with(true)->once()->andReturnSelf();
        $mockMessageQuery->shouldReceive('uid')->with('789')->once()->andReturnSelf();
        $mockMessageQuery->shouldReceive('get')->once()->andReturn($mockMessageCollection);

        // Mock collection behavior
        $mockMessageCollection->shouldReceive('count')->andReturn(1);
        $mockMessageCollection->shouldReceive('first')->andReturn($mockMessage);

        // Set up message expectations
        $mockMessage->shouldReceive('getAttributes')->andReturn([
            'uid' => [789],
            'subject' => ['Personal Email Subject'],
            'from' => ['friend@personal.com'],
            'date' => ['2025-06-26 14:00:00'],
            'message_id' => ['<message-789@personal.com>'],
            'to' => ['me@example.com'],
            'cc' => [],
        ]);
        $mockMessage->shouldReceive('getTextBody')->andReturn('Email body content');
        $mockMessage->shouldReceive('getHTMLBody')->andReturn('<p>Email body content</p>');

        // Create ImapClient instance and call getEmail with personal account
        $imapClient = new ImapClient();
        $email = $imapClient->getEmail('789', 'smtp2');

        // Assert result
        $this->assertEquals('789', $email['id']);
        $this->assertEquals('Personal Email Subject', $email['subject']);
        $this->assertEquals('friend@personal.com', $email['from']);
        $this->assertArrayHasKey('body', $email);
    }
}
