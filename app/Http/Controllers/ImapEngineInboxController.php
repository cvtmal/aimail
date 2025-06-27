<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\AIClientInterface;
use App\Contracts\MailerServiceInterface;
use App\Models\EmailReply;
use App\Services\ImapEngineClient;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ImapEngineInboxController extends Controller
{
    public function __construct(
        private readonly ImapEngineClient $imapClient,
        private readonly AIClientInterface $aiClient,
        private readonly MailerServiceInterface $mailerService,
    ) {}

    /**
     * Display a listing of ImapEngine inbox emails.
     */
    public function index(Request $request): Response
    {
        $account = $request->query('account');
        $emails = $this->imapClient->getInboxEmails($account);

        return Inertia::render('ImapEngineInbox/Index', [
            'emails' => $emails,
            'account' => $account ?? 'default',
        ]);
    }

    /**
     * Display the specified email.
     */
    public function show(Request $request, string $id): Response
    {
        $account = $request->query('account');
        $accountId = $account ?? config('imapengine.default', 'default');
        $email = $this->imapClient->getEmail($id, $accountId);

        if (! $email) {
            return Inertia::render('ImapEngineInbox/Show', [
                'email' => null,
                'error' => 'Email not found',
                'account' => $account ?? 'default',
            ]);
        }

        // Load existing draft / history
        $reply = EmailReply::query()
            ->where('email_id', $id)
            ->where(function ($q) use ($accountId) {
                $q->where('account', $accountId)->orWhereNull('account');
            })
            ->first();

        return Inertia::render('ImapEngineInbox/Show', [
            'email' => $email,
            'latestReply' => $reply?->latest_ai_reply,
            'chatHistory' => $reply?->chat_history ?? [],
            'signature' => config('signatures.'.$accountId) ?? config('signatures.default'),
            'account' => $accountId,
        ]);
    }

    /**
     * Generate an AI reply for an email.
     */
    public function generateReply(Request $request, string $id)
    {
        $validated = $request->validate([
            'instruction' => ['required', 'string'],
        ]);

        $account = $request->query('account');
        $accountId = $account ?? config('imapengine.default', 'default');

        $email = $this->imapClient->getEmail($id, $accountId);
        if (! $email) {
            return Inertia::render('ImapEngineInbox/Show', [
                'email' => null,
                'account' => $accountId,
                'message' => 'Email not found',
                'success' => false,
            ]);
        }

        $reply = EmailReply::query()
            ->where('email_id', $id)
            ->where(function ($query) use ($accountId) {
                $query->where('account', $accountId)->orWhereNull('account');
            })
            ->first();
        $history = $reply?->chat_history ?? [];

        $result = $this->aiClient->generateReply($email, $validated['instruction'], $history);

        $this->mailerService->saveDraftReply($id, $result['reply'], $result['chat_history'], $accountId);

        return Inertia::render('ImapEngineInbox/Show', [
            'email' => $email,
            'latestReply' => $result['reply'],
            'chatHistory' => $result['chat_history'],
            'signature' => config('signatures.'.$accountId) ?? config('signatures.default'),
            'message' => 'Reply generated successfully.',
            'success' => true,
            'account' => $accountId,
        ]);
    }

    /**
     * Send the AI reply.
     */
    public function sendReply(Request $request, string $id)
    {
        $validated = $request->validate([
            'reply' => ['required', 'string'],
            'signature' => ['nullable', 'string'],
        ]);

        $account = $request->query('account');
        $accountId = $account ?? config('imapengine.default', 'default');

        $email = $this->imapClient->getEmail($id, $accountId);
        if (! $email) {
            return Inertia::render('ImapEngineInbox/Show', [
                'email' => null,
                'account' => $accountId,
                'message' => 'Email not found',
                'success' => false,
            ]);
        }

        $signature = mb_trim($validated['signature'] ?? '');
        $combined = mb_trim($validated['reply']);
        if ($signature !== '') {
            $combined .= "\n\n".$signature;
        }

        EmailReply::updateOrCreate(
            ['email_id' => $id, 'account' => $accountId],
            ['latest_ai_reply' => $combined, 'sent_at' => now()]
        );

        $sent = $this->mailerService->sendReply($email, $combined, $accountId);

        if ($sent) {
            return to_route('imapengine.inbox.index', ['account' => $accountId])
                ->with('message', 'Reply sent successfully')
                ->with('success', true);
        }

        return Inertia::render('ImapEngineInbox/Show', [
            'email' => $email,
            'latestReply' => $validated['reply'],
            'signature' => config('signatures.'.$accountId) ?? config('signatures.default'),
            'message' => 'Failed to send reply. Please try again.',
            'success' => false,
            'account' => $accountId,
        ]);
    }
}
