<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendBroadcastMessage;
use App\Models\InternetPackage;
use App\Models\Subscription;
use App\Services\Hotspot\HotspotAccountService;

/**
 * Issues internet-access credentials (username/password) for a subscription and
 * delivers them to the member by email and SMS using the workspace's configured
 * messaging accounts. The credentials live in the subscription's hotspot account
 * (HotspotAccountService), which also pushes them to the workspace's routers.
 */
class InternetAccessService
{
    public function __construct(
        private readonly MessagingSettingsService $settings,
        private readonly HotspotAccountService $accounts,
    ) {}

    /**
     * Generate (or regenerate) and send the member's internet credentials.
     *
     * @return array{username: string, password: string, sent_email: bool, sent_sms: bool}
     */
    public function provision(Subscription $subscription, bool $sendEmail = true, ?InternetPackage $package = null): array
    {
        $subscription->loadMissing('member', 'workspace');

        $password = $this->randomPassword();
        $account = $this->accounts->provision($subscription, $password, $package);
        $subscription->unsetRelation('hotspotAccount');

        $delivery = $this->deliver($subscription, $account->username, $password, $sendEmail);

        return [
            'username' => $account->username,
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

        if ($member === null || $workspace === null || $member->isDemo()) {
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
