<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\BroadcastMessageMail;
use App\Services\Sms\SmsService;
use App\Support\MailConfigurator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Delivers ONE broadcast message to ONE recipient on ONE channel, off the
 * request thread. The composer's request resolves the audience and the
 * decrypted sender config once, then fans out a job per recipient/channel so a
 * single bad address never fails the whole broadcast and the HTTP request
 * returns immediately.
 *
 * The decrypted SMTP/SMS config is carried on the job payload (database queue,
 * trusted infrastructure) so each worker sends through the exact account the
 * composer was entitled to — never re-reading global mail config.
 */
class SendBroadcastMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Retry a transient transport/gateway failure twice. */
    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  'email'|'sms'  $channel
     * @param  array<string, mixed>  $config  decrypted SMTP (email) or SMS config
     */
    public function __construct(
        private readonly string $channel,
        private readonly string $to,
        private readonly string $subject,
        private readonly string $body,
        private readonly array $config,
    ) {}

    public function handle(SmsService $sms): void
    {
        try {
            if ($this->channel === 'email') {
                $this->sendEmail();

                return;
            }

            $this->sendSms($sms);
        } catch (Throwable $e) {
            // Let the queue retry; log so a persistent failure is visible.
            Log::warning('Broadcast send failed', [
                'channel' => $this->channel,
                'to' => $this->to,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function sendEmail(): void
    {
        $mailer = MailConfigurator::apply($this->config);

        Mail::mailer($mailer)
            ->to($this->to)
            ->send(new BroadcastMessageMail($this->subject, $this->body));
    }

    private function sendSms(SmsService $sms): void
    {
        $result = $sms->send($this->to, $this->body, $this->config);

        if ($result['ok'] !== true) {
            throw new \RuntimeException(
                'SMS gateway rejected the message: '.(is_string($result['response'])
                    ? $result['response']
                    : (string) json_encode($result['response'])),
            );
        }
    }
}
