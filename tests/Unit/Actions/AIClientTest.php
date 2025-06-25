<?php

declare(strict_types=1);

use App\Models\EmailReply;
use App\Services\AIClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->mockHttp = Mockery::mock(Factory::class);
    $this->aiClient = new AIClient($this->mockHttp);
});

afterEach(function () {
    Mockery::close();
});

test('generateReply makes correct API call', function () {
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => 'This is a test AI reply.'
                    ]
                ]
            ]
        ], 200),
    ]);

    $email = [
        'id' => 'test-id',
        'subject' => 'Test Subject',
        'from' => 'test@example.com',
        'body' => 'Test email content.',
        'date' => now(),
    ];
    
    $userInstruction = 'Please reply professionally.';

    $reply = app(AIClient::class)->generateReply($email, $userInstruction);
    
    expect($reply)->toBe('This is a test AI reply.');
    
    Http::assertSent(function (Request $request) {
        $data = $request->data();
        $messages = $data['messages'] ?? [];
        
        return $request->url() == 'https://api.openai.com/v1/chat/completions' &&
            $request->hasHeader('Authorization', 'Bearer '.config('services.ai.key')) &&
            count($messages) >= 2 &&
            $messages[0]['role'] === 'system';
    });
});

test('generateReply with chat history includes previous conversations', function () {
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => 'This is a follow-up reply.'
                    ]
                ]
            ]
        ], 200),
    ]);

    $email = [
        'id' => 'test-id',
        'subject' => 'Test Subject',
        'from' => 'test@example.com',
        'body' => 'Test email content.',
        'date' => now(),
    ];
    
    $userInstruction = 'Make it more formal.';
    
    $chatHistory = [
        [
            'role' => 'system',
            'content' => 'You are an email assistant.'
        ],
        [
            'role' => 'user',
            'content' => 'Please draft a reply.'
        ],
        [
            'role' => 'assistant',
            'content' => 'Here is a draft reply.'
        ]
    ];

    $emailReply = EmailReply::factory()->create([
        'email_id' => 'test-id',
        'chat_history' => $chatHistory,
        'latest_ai_reply' => 'Here is a draft reply.'
    ]);

    $reply = app(AIClient::class)->generateReply($email, $userInstruction);
    
    expect($reply)->toBe('This is a follow-up reply.');
    
    Http::assertSent(function (Request $request) use ($userInstruction) {
        $data = $request->data();
        $messages = $data['messages'] ?? [];
        $lastMessage = end($messages);
        
        return $lastMessage['role'] === 'user' && 
               $lastMessage['content'] === $userInstruction;
    });
});

test('addToChatHistory properly formats and stores conversation history', function () {
    $emailId = 'test-id';
    $userInstruction = 'Please be professional.';
    $aiReply = 'This is a professional response.';
    
    $aiClient = app(AIClient::class);
    $aiClient->addToChatHistory($emailId, $userInstruction, $aiReply);
    
    $emailReply = EmailReply::where('email_id', $emailId)->first();
    
    expect($emailReply)->not->toBeNull();
    expect($emailReply->chat_history)->toHaveCount(3); // system + user + assistant
    expect($emailReply->chat_history[1]['role'])->toBe('user');
    expect($emailReply->chat_history[1]['content'])->toBe($userInstruction);
    expect($emailReply->chat_history[2]['role'])->toBe('assistant');
    expect($emailReply->chat_history[2]['content'])->toBe($aiReply);
});
