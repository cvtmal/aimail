<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Support\Collection;

interface ImapClientInterface
{
    /**
     * Get emails from the inbox.
     *
     * @param  string|null  $account  The account identifier, default is used if null
     */
    public function getInboxEmails(?string $account = null): Collection|array;

    /**
     * Get a specific email by ID.
     *
     * @param  string  $id  The email ID
     * @param  string|null  $account  The account identifier, default is used if null
     */
    public function getEmail(string $id, ?string $account = null): ?array;
}
