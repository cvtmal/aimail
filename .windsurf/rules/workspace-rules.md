---
trigger: always_on
---

You are a full-stack Laravel developer. You follow the global windsurf ruleset.

I want you to generate an application with the following functionality:

## Purpose

An email assistant that reads emails from an IMAP inbox, displays them in a web UI, allows me to instruct an AI to generate replies, lets me review, regenerate, and edit replies conversationally — and send those replies via SMTP.

## Stack

- Laravel 12
- PHP 8.4
- Inertiajs & React for frontend
- `webklex/laravel-imap` package to read IMAP emails
- Laravel built-in Mail functionality to send SMTP emails
- Laravel HTTP Client or Guzzle to call AI model API (OpenAI)

## App Features

1. **Inbox Page** `/inbox`
    - Lists all inbox emails: Subject, From, Date
    - Clickable to view full email

2. **Email View Page** `/inbox/{id}`
    - Shows full email content
    - Textarea + "AI prompt": user types instruction (e.g. "Answer in friendly tone")
    - Button "Generate AI reply"
    - Displays suggested reply from AI
    - Reply shown inside editable `<textarea>` for manual corrections
    - Button "Send email"

3. **Regenerate AI reply**
    - Button "Regenerate" — sends the same email content and chat history + new user instruction to AI
    - The AI should understand conversational context — e.g., "I like the reply but make it more formal," or "Make it shorter."
    - Chat history between user and AI should be preserved per email conversation

4. **Send Reply**
    - Sends reply via SMTP using Laravel Mail
    - Success confirmation
    - Save sent reply to database

5. **Optional: Save draft reply**
    - Allow user to save AI reply as a draft

## Services

- `ImapClient` service class
    - Connects to IMAP server
    - Lists emails
    - Fetches single email by ID

- `AIClient` service class
    - Manages chat history per email (conversation state)
    - Sends email content and user instructions to AI model
    - Returns AI-generated reply
    - Supports appending conversational context (e.g., "Make it more formal")

- `MailerService` class
    - Sends email via Laravel Mail

## Data Persistence

- Database table `email_replies`:
    - `id`
    - `email_id`
    - `chat_history` (JSON)
    - `latest_ai_reply`
    - `sent_at`

## .env Requirements

- IMAP configuration:
    - IMAP_HOST
    - IMAP_PORT
    - IMAP_ENCRYPTION
    - IMAP_USERNAME
    - IMAP_PASSWORD

- SMTP configuration:
    - SMTP_HOST
    - SMTP_PORT
    - SMTP_USERNAME
    - SMTP_PASSWORD
    - SMTP_ENCRYPTION

- AI API configuration:
    - AI_API_URL
    - AI_API_KEY

## Deliverables

- Full Laravel project
- Example inbox UI `/inbox`
- Example email view `/inbox/{id}`
- Conversational AI reply feature
- Example controller: `InboxController`
- Example services: `ImapClient`, `AIClient`, `MailerService`
- Seed data or mock email option if no IMAP connected

## Key Focus

The app must preserve conversational context so AI can handle follow-up instructions like:

- "Make it more formal"
- "Give me a shorter version"
- "Rewrite as if I am the team lead"

---

The generated app should focus on functionality first, visual style is secondary. For visual styling follow the global windsurf ruleset.
