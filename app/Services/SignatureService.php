<?php

declare(strict_types=1);

namespace App\Services;

final readonly class SignatureService
{
    /**
     * Return the HTML signature block for a given account.
     */
    public function get(string $accountId): string
    {
        return config("signatures.$accountId") ?? config('signatures.default', '');
    }
}
