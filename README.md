# AI Mail Assistant

An email assistant application that reads emails from an IMAP inbox, displays them in a web UI, allows for AI-generated replies with conversational context, and sends responses via SMTP.

## Features

- **IMAP Integration**: Connect to any IMAP email server to read inbox messages
- **AI-Powered Replies**: Use AI to generate contextual email replies
- **Conversational Context**: Continue refining AI responses with natural language instructions
- **SMTP Integration**: Send replies directly via SMTP
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
   git clone [repository-url]
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

   - SMTP settings:
     ```
     SMTP_HOST=smtp.example.com
     SMTP_PORT=587
     SMTP_USERNAME=your-username
     SMTP_PASSWORD=your-password
     SMTP_ENCRYPTION=tls
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
   php artisan serve
   ```

10. **Access the application**

    Visit `http://localhost:8000/inbox` in your browser

## Development Notes

- For local development without an actual IMAP connection, the app will use a mock IMAP client that provides sample emails
- The `.env` configuration determines whether to use the real or mock IMAP client
- Tests are written using Pest PHP

## Testing

```bash
php artisan test
```

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
