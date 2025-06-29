# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### Backend (Laravel)
- `composer run dev` - Start development server with queue, logs, and Vite (recommended)
- `composer run dev:ssr` - Start development server with SSR enabled
- `composer run test` - Run PHP tests (config clear + test)
- `php artisan serve` - Start Laravel development server only
- `php artisan queue:listen --tries=1` - Start queue worker
- `php artisan pail --timeout=0` - Real-time log monitoring
- `php artisan migrate` - Run database migrations
- `php artisan db:seed --class=EmailReplySeeder` - Seed development data

### Frontend (React/TypeScript)
- `npm run dev` - Start Vite development server
- `npm run build` - Build for production
- `npm run build:ssr` - Build with SSR support
- `npm run lint` - Run ESLint with auto-fix
- `npm run format` - Format code with Prettier
- `npm run format:check` - Check code formatting
- `npm run types` - TypeScript type checking without emit

### Testing
- `composer run test` - Run all PHP tests using Pest
- Tests use SQLite in-memory database for speed
- Feature tests in `tests/Feature/`, Unit tests in `tests/Unit/`

## Architecture Overview

This is an AI-powered email assistant application built with Laravel 12 + Inertia.js + React that handles IMAP email reading, AI-generated replies, and SMTP sending.

### Backend Structure (Laravel)
- **Controllers**: `ImapEngineInboxController` (new IMAP engine) and `InboxController` (legacy)
- **Services**: 
  - `AIClient` - OpenAI GPT-4 integration for generating email replies
  - `ImapEngineClient` - New IMAP client using directorytree/imapengine-laravel
  - `ImapClient` - Legacy IMAP client using webklex/laravel-imap
  - `MailerService` - SMTP email sending with multi-account support
  - `SignatureService` - Email signature management
- **Models**: `EmailReply` (stores AI replies and chat history), `User`
- **Contracts**: Interface-based architecture for services (AIClientInterface, etc.)

### Multi-Account Email Support
The application supports multiple email accounts configured via environment variables:
- Default account (IMAP_*, SMTP_*)
- Additional accounts (IMAP_DAMIAN_*, SMTP1_*, etc.)
- Account-specific routes: `/accounts/{account}/*`
- Account parameter passed throughout the application flow

### Frontend Structure (React/TypeScript)
- **Framework**: Inertia.js with React 19, TypeScript, TailwindCSS 4
- **UI Components**: Radix UI primitives with custom styled components in `components/ui/`
- **Pages**: `ImapEngineInbox/` (new implementation), `Inbox/` (legacy), auth pages
- **Layouts**: `AppLayout` with sidebar navigation, auth layouts
- **State Management**: Inertia forms with React hooks
- **Styling**: TailwindCSS with dark mode support

### Key Features
- **IMAP Integration**: Connect to multiple email accounts, read inbox messages
- **AI-Powered Replies**: Generate contextual email replies using GPT-4 with conversational refinement
- **Chat History**: Maintain conversation context between AI interactions
- **SMTP Sending**: Send replies via configured SMTP accounts
- **Multi-Account**: Support for multiple email accounts with account-specific configurations

### Database Schema
- `email_replies` table stores AI-generated replies, chat history (JSON), and account associations
- Uses SQLite for development, configurable for production

### Configuration Files
- `config/imapengine.php` - New IMAP engine configuration with multiple mailboxes
- `config/imap.php` - Legacy IMAP configuration
- `config/mail.php` - SMTP mailer configurations for multiple accounts
- `config/signatures.php` - Email signatures per account
- `config/services.php` - AI API configuration (OpenAI)

### Path Aliases
- `@/*` maps to `resources/js/*` in TypeScript
- Uses Laravel Vite plugin for asset building
- SSR support available with `resources/js/ssr.tsx`

### Code Style
- PHP: Uses Laravel Pint for formatting, strict type declarations
- TypeScript: ESLint + Prettier with import organization
- Uses Pest for PHP testing with feature/unit test separation