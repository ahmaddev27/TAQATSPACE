<?php

declare(strict_types=1);

namespace App\Services\Sms;

/**
 * A single SMS gateway transport. Implementations wrap one provider's HTTP send
 * API. They are deliberately fail-soft: a driver must never throw to the caller
 * — network/credential failures are reported through the returned array so the
 * send pipeline (and the admin "test" endpoint) can surface a friendly message
 * instead of a 500.
 */
interface SmsDriver
{
    /**
     * Send a single text message.
     *
     * @return array{ok: bool, response: mixed} `ok` is the delivery outcome;
     *                                           `response` carries the provider
     *                                           payload (or error message) for
     *                                           diagnostics — never a secret.
     */
    public function send(string $to, string $message): array;
}
