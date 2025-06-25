<?php

declare(strict_types=1);

use App\Models\EmailReply;
use App\Models\User;
use App\Services\AIClient;
use App\Services\ImapClient;
use App\Services\MailerService;
use App\Services\MockImapClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->mockImapClient = new MockImapClient();
    $this->mockAiClient = Mockery::mock(AIClient::class);
    $this->mockMailerService = Mockery::mock(MailerService::class);
    
    $this->app->instance(ImapClient::class, $this->mockImapClient);
    $this->app->instance(AIClient::class, $this->mockAiClient);
    $this->app->instance(MailerService::class, $this->mockMailerService);
});

test('inbox index displays emails', function () {
    $response = $this->actingAs($this->user)
        ->get('/inbox');
        
    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Inbox/Index')
            ->has('emails', 5)); // Our MockImapClient returns 5 emails
});

test('show email displays email details and possible existing reply', function () {
    // Create a previous reply
    EmailReply::factory()->create([
        'email_id' => 'email-001',
        'latest_ai_reply' => 'Previous AI reply content',
        'chat_history' => [
            ['role' => 'system', 'content' => 'You are a helpful assistant'],
            ['role' => 'user', 'content' => 'Draft a reply'],
            ['role' => 'assistant', 'content' => 'Previous AI reply content']
        ]
    ]);

    $response = $this->actingAs($this->user)
        ->get('/inbox/email-001');
        
    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Inbox/Show')
            ->has('email')
            ->has('emailReply')
            ->where('email.id', 'email-001')
            ->where('email.subject', 'Project Update: Q3 Goals')
            ->where('emailReply.latest_ai_reply', 'Previous AI reply content'));
});

test('show returns not found when email does not exist', function () {
    $response = $this->actingAs($this->user)
        ->get('/inbox/non-existent-email');
        
    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('Inbox/NotFound'));
});

test('generate reply creates ai response and saves chat history', function () {
    $emailId = 'email-001';
    $instruction = 'Please draft a professional reply';
    $aiReplyText = 'This is a professional AI-generated reply';
    
    // Setup mock response from AI client
    $this->mockAiClient->shouldReceive('generateReply')
        ->once()
        ->andReturn($aiReplyText);
    
    $this->mockAiClient->shouldReceive('addToChatHistory')
        ->once()
        ->with($emailId, $instruction, $aiReplyText)
        ->andReturn(true);
        
    $response = $this->actingAs($this->user)
        ->post("/inbox/{$emailId}/generate-reply", [
            'instruction' => $instruction,
        ]);
        
    $response->assertStatus(200)
        ->assertJson([
            'reply' => $aiReplyText,
        ]);
});

test('send reply sends email via mailer service', function () {
    $emailId = 'email-001';
    $replyContent = 'This is my reply to the email';
    
    // Create a mock email result
    $email = $this->mockImapClient->getEmail($emailId);
    
    $this->mockMailerService->shouldReceive('sendReply')
        ->once()
        ->with(
            Mockery::on(function ($arg) use ($emailId) {
                return $arg['id'] === $emailId;
            }),
            $replyContent
        )
        ->andReturn(true);
        
    $response = $this->actingAs($this->user)
        ->post("/inbox/{$emailId}/send-reply", [
            'reply' => $replyContent,
        ]);
        
    $response->assertStatus(302)
        ->assertRedirect('/inbox');
        
    $this->assertDatabaseHas('email_replies', [
        'email_id' => $emailId,
        'latest_ai_reply' => $replyContent,
    ]);
});
