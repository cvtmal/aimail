# AI Mail Assistant

An email assistant application that reads emails from an IMAP inbox, displays them in a web UI, allows for AI-generated replies with conversational context, and sends responses via SMTP.

## Features

- **IMAP Integration**: Connect to any IMAP email server to read inbox messages
- **AI-Powered Replies**: Use AI to generate contextual email replies
- **Conversational Context**: Continue refining AI responses with natural language instructions
- **SMTP Integration**: Send replies directly via SMTP
- **Multi-Account Support**: Use multiple email accounts with the application
- **Modern UI**: Clean interface built with Laravel 12, Inertia.js, and React

## Tech Stack

- Laravel 12
- PHP 8.4
- Inertia.js & React for frontend
- webklex/laravel-imap for IMAP connectivity
- Laravel Mail for SMTP
- OpenAI GPT-4 integration (or compatible API)

## Setup Instructions

1. **Clone the repository**

   ```bash
   git clone https://github.com/cvtmal/aimail
   cd aimail
   ```

2. **Install PHP dependencies**

   ```bash
   composer install
   ```

3. **Install JavaScript dependencies**

   ```bash
   npm install
   ```

4. **Copy environment file and configure**

   ```bash
   cp .env.example .env
   ```

   Update the following sections in your `.env` file:

   - IMAP settings:
     ```
     IMAP_HOST=imap.example.com
     IMAP_PORT=993
     IMAP_ENCRYPTION=ssl
     IMAP_USERNAME=your-username
     IMAP_PASSWORD=your-password
     ```

   - SMTP settings (for multiple accounts):
     ```
     # Default account
     SMTP_HOST=smtp.example.com
     SMTP_PORT=587
     SMTP_USERNAME=your-username
     SMTP_PASSWORD=your-password
     SMTP_ENCRYPTION=tls
     MAIL_FROM_ADDRESS=default@example.com
     MAIL_FROM_NAME="Default Account"
     
     # Additional account 1
     SMTP1_MAIL_HOST=smtp1.example.com
     SMTP1_MAIL_PORT=587
     SMTP1_MAIL_USERNAME=username1
     SMTP1_MAIL_PASSWORD=password1
     SMTP1_MAIL_ENCRYPTION=tls
     SMTP1_MAIL_FROM_ADDRESS=account1@example.com
     SMTP1_MAIL_FROM_NAME="Account 1"
     
     # Additional account 2
     SMTP2_MAIL_HOST=smtp2.example.com
     SMTP2_MAIL_PORT=587
     SMTP2_MAIL_USERNAME=username2
     SMTP2_MAIL_PASSWORD=password2
     SMTP2_MAIL_ENCRYPTION=tls
     SMTP2_MAIL_FROM_ADDRESS=account2@example.com
     SMTP2_MAIL_FROM_NAME="Account 2"
     ```

   - AI API settings:
     ```
     AI_API_URL=https://api.openai.com/v1/chat/completions
     AI_API_KEY=your-openai-api-key
     ```

5. **Generate application key**

   ```bash
   php artisan key:generate
   ```

6. **Run database migrations**

   ```bash
   php artisan migrate
   ```

7. **Seed development data (optional)**

   ```bash
   php artisan db:seed --class=EmailReplySeeder
   ```

8. **Build frontend assets**

   ```bash
   npm run build
   ```

9. **Start the development server**

   ```bash
   composer run dev
   ```

## Development Notes

- For local development without an actual IMAP connection, the app will use a mock IMAP client that provides sample emails
- The `.env` configuration determines whether to use the real or mock IMAP client
- Tests are written using Pest PHP

## Multi-Account Support

### Configuration

1. **IMAP Accounts**

   Multiple IMAP accounts are configured in `config/imap.php`. Each account needs its own set of credentials.

2. **SMTP Accounts**

   Multiple SMTP accounts are defined in `config/mail.php` under the 'mailers' array:
   - `smtp` (default account)
   - `smtp1` (additional account 1)
   - `smtp2` (additional account 2)

### Usage in Backend

The system supports account-specific operations throughout the application. All key services accept an optional account identifier:

```php
// Using the default account
$emails = $imapClient->getInboxEmails();

// Using a specific account
$emails = $imapClient->getInboxEmails('smtp1');
$mailerService->sendReply($reply, $emailId, $chatHistory, 'smtp2');
```

### Database Schema

The `email_replies` table includes an `account` column that stores which account each email reply belongs to, enabling proper filtering and organization.

## Testing

```bash
php artisan test
```

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
