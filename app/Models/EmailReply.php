<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class EmailReply extends Model
{
    /** @use HasFactory<\Database\Factories\EmailReplyFactory> */
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'chat_history' => 'json',
        'sent_at' => 'datetime',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email_id',
        'account',
        'latest_ai_reply',
        'chat_history',
        'sent_at',
    ];
}
