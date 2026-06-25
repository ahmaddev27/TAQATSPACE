<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PartnerClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Mints a partner client (e.g. Academy) and prints its plaintext API key and
 * webhook secret ONCE. Only the key hash is stored; the secret is stored to
 * sign outbound webhooks. Share both with the partner over a secure channel.
 */
class CreatePartnerClient extends Command
{
    protected $signature = 'partner:create
        {name : Human-readable partner name}
        {--webhook-url= : Optional URL we POST booking events to}';

    protected $description = 'Create a partner API client and print its credentials once';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $apiKey = Str::random(48);
        $webhookSecret = Str::random(40);

        $partner = PartnerClient::query()->create([
            'name' => $name,
            'api_key_hash' => PartnerClient::hashKey($apiKey),
            'webhook_url' => $this->option('webhook-url'),
            'webhook_secret' => $webhookSecret,
            'is_active' => true,
            'scopes' => ['membership.read'],
        ]);

        $this->info("Partner client created: {$partner->name} ({$partner->id})");
        $this->newLine();
        $this->warn('Store these now — they are shown only once:');
        $this->line("  API key        (X-Api-Key): {$apiKey}");
        $this->line("  Webhook secret (HMAC)     : {$webhookSecret}");

        return self::SUCCESS;
    }
}
