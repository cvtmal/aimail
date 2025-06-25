<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EmailReply;
use Illuminate\Database\Seeder;

final class EmailReplySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 10 sample email replies
        EmailReply::factory()
            ->count(10)
            ->create();

        // Create a few email replies with specific email IDs for testing
        // These IDs match some of the mock emails we'll use in the mock IMAP service
        $testEmailIds = [
            'email-001',
            'email-002',
            'email-003',
        ];

        foreach ($testEmailIds as $emailId) {
            EmailReply::factory()
                ->create([
                    'email_id' => $emailId,
                ]);
        }
    }
}
