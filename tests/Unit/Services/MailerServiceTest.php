<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Mail\EmailReplyMailable;
use App\Models\EmailReply;
use App\Services\MailerService;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class MailerServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function testSendReplyWithDefaultAccount(): void
    {
        // Create an email reply record
        $emailReply = EmailReply::factory()->make([
            'email_id' => '123',
            'latest_ai_reply' => 'Test reply content',
            'chat_history' => [['role' => 'assistant', 'content' => 'Test reply content']],
            'sent_at' => null,
        ]);

        $mailerService = new MailerService();
        
        // Test parameters
        $to = 'recipient@example.com';
        $subject = 'RE: Test Email';
        $aiReply = 'Test reply content';
        $originalEmailId = '123';
        $html = '<p>Test reply content</p>';
        $chatHistory = [['role' => 'assistant', 'content' => 'Test reply content']];
        
        // Send the reply
        $mailerService->sendReply($to, $subject, $aiReply, $originalEmailId, $chatHistory, $html);
        
        // Assert that the email was sent with default mailer
        Mail::assertSent(EmailReplyMailable::class, function ($mail) use ($to, $subject, $aiReply, $html) {
            return $mail->hasTo($to) && 
                   $mail->subject === $subject && 
                   $mail->content === $aiReply &&
                   $mail->html === $html &&
                   $mail->account === null; // Default account
        });
        
        // Assert that an EmailReply record was saved with sent_at not null
        $this->assertDatabaseHas('email_replies', [
            'email_id' => '123',
            'account' => null, // Default account
            'latest_ai_reply' => 'Test reply content',
        ]);
    }
    
    public function testSendReplyWithSpecificAccount(): void
    {
        // Create an email reply record
        $emailReply = EmailReply::factory()->make([
            'email_id' => '456',
            'account' => 'smtp1',
            'latest_ai_reply' => 'Work reply content',
            'chat_history' => [['role' => 'assistant', 'content' => 'Work reply content']],
            'sent_at' => null,
        ]);
        
        $mailerService = new MailerService();
        
        // Test parameters
        $to = 'colleague@work.com';
        $subject = 'RE: Work Email';
        $aiReply = 'Work reply content';
        $originalEmailId = '456';
        $html = '<p>Work reply content</p>';
        $account = 'smtp1';
        $chatHistory = [['role' => 'assistant', 'content' => 'Work reply content']];
        
        // Send the reply with work account
        $mailerService->sendReply($to, $subject, $aiReply, $originalEmailId, $chatHistory, $html, $account);
        
        // Assert that the email was sent with work mailer
        Mail::assertSent(EmailReplyMailable::class, function ($mail) use ($to, $subject, $aiReply, $html, $account) {
            return $mail->hasTo($to) && 
                   $mail->subject === $subject && 
                   $mail->content === $aiReply &&
                   $mail->html === $html &&
                   $mail->account === $account;
        });
        
        // Assert that an EmailReply record was saved with correct account
        $this->assertDatabaseHas('email_replies', [
            'email_id' => '456',
            'account' => 'smtp1',
            'latest_ai_reply' => 'Work reply content',
        ]);
    }
    
    public function testSaveDraftReplyWithDifferentAccounts(): void
    {
        $mailerService = new MailerService();
        
        // Test with default account
        $mailerService->saveDraftReply(
            'Default draft content',
            '123',
            [['role' => 'assistant', 'content' => 'Default draft content']]
        );
        
        // Assert default account draft was saved
        $this->assertDatabaseHas('email_replies', [
            'email_id' => '123',
            'account' => null,
            'latest_ai_reply' => 'Default draft content',
            'sent_at' => null,
        ]);
        
        // Test with personal account
        $mailerService->saveDraftReply(
            'Personal draft content',
            '789',
            [['role' => 'assistant', 'content' => 'Personal draft content']],
            'smtp2'
        );
        
        // Assert personal account draft was saved
        $this->assertDatabaseHas('email_replies', [
            'email_id' => '789',
            'account' => 'smtp2',
            'latest_ai_reply' => 'Personal draft content',
            'sent_at' => null,
        ]);
    }
}
