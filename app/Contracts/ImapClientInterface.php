<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Support\Collection;

interface ImapClientInterface
{
    /**
     * Get emails from the inbox.
     *
     * @return Collection|array
     */
    public function getInboxEmails(): Collection|array;

    /**
     * Get a specific email by ID.
     *
     * @param string $id
     * @return array|null
     */
    public function getEmail(string $id): ?array;
}
