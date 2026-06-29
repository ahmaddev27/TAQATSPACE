<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendBroadcastMessage;
use App\Models\Subscription;
use Illuminate\Support\Str;

/**
 * Issues internet-access credentials (username/password) for a subscription and
 * delivers them to the member by email and SMS using the workspace's configured
 * messaging accounts. Provisioning the credentials on the actual router is a
 * later phase — this service owns generation, storage and delivery only.
 */
class InternetAccessService
{
    public function __construct(
        private readonly MessagingSettingsService $settings,
    ) {}

    /**
     * Generate (or regenerate) and send the member's internet credentials.
     *
     * @return array{username: string, password: string, sent_email: bool, sent_sms: bool}
     */
    public function provision(Subscription $subscription, bool $sendEmail = true): array
    {
        $subscription->loadMissing('member', 'workspace');

        $username = $subscription->internet_username ?? $this->uniqueUsername($subscription);
        $password = $this->randomPassword();

        $subscription->forceFill([
            'internet_username' => $username,
            'internet_password_enc' => $password, // 'encrypted' cast stores it ciphered
            'internet_provisioned_at' => now(),
        ])->save();

        $delivery = $this->deliver($subscription, $username, $password, $sendEmail);

        return [
            'username' => $username,
            'password' => $password,
            'sent_email' => $delivery['email'],
            'sent_sms' => $delivery['sms'],
        ];
    }

    /**
     * Queue the credential message to the member over each channel the workspace
     * has configured and the member can receive on.
     *
     * @return array{email: bool, sms: bool}
     */
    private function deliver(Subscription $subscription, string $username, string $password, bool $sendEmail = true): array
    {
        $member = $subscription->member;
        $workspace = $subscription->workspace;

        if ($member === null || $workspace === null) {
            return ['email' => false, 'sms' => false];
        }

        $config = $this->settings->forWorkspace($workspace);
        $subject = __('messages.internet_credentials_subject', ['workspace' => $workspace->name]);
        $body = __('messages.internet_credentials_body', [
            'workspace' => $workspace->name,
            'username' => $username,
            'password' => $password,
        ]);

        $sentEmail = false;
        $sentSms = false;

        // On booking approval the branded approval email carries the credentials,
        // so the caller suppresses this plain copy ($sendEmail = false) to avoid
        // a duplicate; SMS still goes out here.
        if ($sendEmail && $member->email !== null && $this->settings->isSmtpConfigured($config['smtp'])) {
            SendBroadcastMessage::dispatch('email', $member->email, $subject, $body, $config['smtp']);
            $sentEmail = true;
        }

        if (! empty($member->phone) && $this->settings->isSmsConfigured($config['sms'])) {
            SendBroadcastMessage::dispatch('sms', (string) $member->phone, $subject, $body, $config['sms']);
            $sentSms = true;
        }

        return ['email' => $sentEmail, 'sms' => $sentSms];
    }

    /**
     * A stable, readable username derived from the member, made unique across
     * subscriptions by appending a short suffix on collision.
     */
    private function uniqueUsername(Subscription $subscription): string
    {
        $member = $subscription->member;
        $base = Str::of((string) ($member?->name ?? $member?->email ?? 'user'))
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]/', '')
            ->substr(0, 12)
            ->value();

        if ($base === '') {
            $base = 'user';
        }

        $candidate = $base;

        while (Subscription::query()->where('internet_username', $candidate)->exists()) {
            $candidate = $base.Str::lower(Str::random(3));
        }

        return $candidate;
    }

    /** A short, human-typeable password (no easily-confused characters). */
    private function randomPassword(): string
    {
        $alphabet = 'abcdefghijkmnpqrstuvwxyz23456789';
        $password = '';

        for ($i = 0; $i < 8; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }
}
