<?php

declare(strict_types=1);

namespace App\Providers;

use DirectoryTree\ImapEngine\Laravel\ImapServiceProvider as BaseImapServiceProvider;
use Illuminate\Support\ServiceProvider;

final class ImapEngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register the ImapEngine service provider
        $this->app->register(BaseImapServiceProvider::class);

        // Merge our custom ImapEngine config into the 'imap' key that the package expects
        $this->app['config']->set('imap', $this->app['config']->get('imapengine'));
    }

    public function boot(): void
    {
        // No boot actions needed
    }
}
