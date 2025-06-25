<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmailReply>
 */
final class EmailReplyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email_id' => $this->faker->uuid(),
            'chat_history' => [
                [
                    'role' => 'system',
                    'content' => 'You are a helpful email assistant.',
                ],
                [
                    'role' => 'user',
                    'content' => 'Please draft a professional response.',
                ],
                [
                    'role' => 'assistant',
                    'content' => $this->faker->paragraph(3),
                ],
            ],
            'latest_ai_reply' => $this->faker->paragraph(4),
            'sent_at' => $this->faker->optional(0.7)->dateTimeThisMonth(),
        ];
    }
}
