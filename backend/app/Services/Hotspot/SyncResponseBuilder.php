<?php

declare(strict_types=1);

namespace App\Services\Hotspot;

/**
 * Builds the plain-text reply a router script consumes (RouterOS 7.12 cannot
 * parse JSON): a `# CWS-SYNC <nonce>` header followed by one allowlisted
 * command per line, capped at 4 KB. The script never runs the text verbatim.
 */
final class SyncResponseBuilder
{
    public const HEADER = '# CWS-SYNC';

    public const MAX_BYTES = 4096;

    /**
     * @param  list<string>  $commands
     */
    public function build(string $nonce, array $commands = []): string
    {
        $body = self::HEADER.' '.$nonce."\n";

        foreach ($commands as $command) {
            $line = $command."\n";

            if (strlen($body) + strlen($line) > self::MAX_BYTES) {
                break;
            }

            $body .= $line;
        }

        return $body;
    }
}
