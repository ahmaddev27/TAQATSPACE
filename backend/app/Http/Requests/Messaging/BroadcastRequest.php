<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use App\Services\Messaging\BroadcastRequestData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared validation for a compose-and-broadcast payload, used by both the admin
 * (platform) and owner (workspace) flows. Authorization is enforced by route
 * middleware; subclasses add their own segment rules.
 *
 * Payload shape:
 *  {
 *    channels: ["email"|"sms", ...],   // at least one
 *    audience: "all" | "segment" | "specific",
 *    segment:  { role?, status? },     // when audience = segment
 *    user_ids: [..],                   // when audience = specific
 *    subject:  string,                 // required when email is selected
 *    body:     string                  // always required (email body + SMS text)
 *  }
 */
abstract class BroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in(['email', 'sms'])],

            'audience' => ['required', Rule::in(['all', 'segment', 'specific'])],

            'user_ids' => ['array'],
            'user_ids.*' => ['string'],

            // Subject is only meaningful for email; required when email is chosen.
            'subject' => [
                Rule::requiredIf(fn (): bool => $this->wantsChannel('email')),
                'nullable',
                'string',
                'max:255',
            ],
            'body' => ['required', 'string', 'max:5000'],
        ], $this->segmentRules());
    }

    /**
     * Segment field rules (role/status vocabularies differ per scope).
     *
     * @return array<string, mixed>
     */
    abstract protected function segmentRules(): array;

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('audience') === 'specific') {
                $ids = $this->input('user_ids');

                if (! is_array($ids) || $ids === []) {
                    $validator->errors()->add('user_ids', __('messages.broadcast_recipients_required'));
                }
            }
        });
    }

    /**
     * Build the normalised DTO the service consumes.
     */
    public function toData(): BroadcastRequestData
    {
        $validated = $this->validated();

        /** @var list<'email'|'sms'> $channels */
        $channels = array_values(array_unique((array) ($validated['channels'] ?? [])));

        /** @var list<string> $userIds */
        $userIds = array_values(array_filter(
            (array) ($validated['user_ids'] ?? []),
            static fn ($id): bool => is_string($id) && $id !== '',
        ));

        return new BroadcastRequestData(
            channels: $channels,
            audience: (string) $validated['audience'],
            segment: [
                'role' => $this->segmentRole($validated),
                'status' => isset($validated['segment']['status'])
                    ? (string) $validated['segment']['status']
                    : null,
            ],
            userIds: $userIds,
            subject: isset($validated['subject']) && $validated['subject'] !== ''
                ? (string) $validated['subject']
                : null,
            body: (string) $validated['body'],
        );
    }

    private function wantsChannel(string $channel): bool
    {
        $channels = $this->input('channels');

        return is_array($channels) && in_array($channel, $channels, true);
    }

    /**
     * Owner segments have no role dimension; admin overrides this.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function segmentRole(array $validated): ?string
    {
        return null;
    }
}
