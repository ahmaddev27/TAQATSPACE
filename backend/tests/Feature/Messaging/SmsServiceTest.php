<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Services\Sms\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Coverage for SMS driver selection: routing to the right provider, failing
 * soft on unknown providers, and never throwing on transport errors.
 */
class SmsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SmsService
    {
        return app(SmsService::class);
    }

    public function test_providers_lists_supported_drivers(): void
    {
        $this->assertSame(['hotsms', 'mtcsms'], SmsService::providers());
    }

    public function test_unknown_provider_fails_soft_without_http_call(): void
    {
        Http::fake();

        $result = $this->service()->send('+970599000000', 'hi', ['provider' => 'nope']);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('nope', (string) $result['response']);
        Http::assertNothingSent();
    }

    public function test_missing_provider_fails_soft(): void
    {
        Http::fake();

        $result = $this->service()->send('+970599000000', 'hi', []);

        $this->assertFalse($result['ok']);
        Http::assertNothingSent();
    }

    public function test_hotsms_provider_hits_hotsms_gateway(): void
    {
        Http::fake([
            'hotsms.ps/*' => Http::response(['status' => 'ok'], 200),
        ]);

        $result = $this->service()->send('+970599000000', 'hello', [
            'provider' => 'hotsms',
            'username' => 'u',
            'password' => 'p',
            'sender' => 'TAQAT',
        ]);

        $this->assertTrue($result['ok']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'hotsms.ps'));
    }

    public function test_mtcsms_provider_does_not_hit_hotsms_gateway(): void
    {
        Http::fake();

        $this->service()->send('+970599000000', 'hello', [
            'provider' => 'mtcsms',
            'username' => 'u',
            'password' => 'p',
            'sender' => 'TAQAT',
        ]);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'hotsms.ps'));
    }

    public function test_gateway_error_response_reports_not_ok(): void
    {
        Http::fake([
            'hotsms.ps/*' => Http::response('rejected', 422),
        ]);

        $result = $this->service()->send('+970599000000', 'hello', [
            'provider' => 'hotsms',
            'username' => 'u',
            'password' => 'p',
        ]);

        $this->assertFalse($result['ok']);
    }

    public function test_driver_never_throws_on_transport_failure(): void
    {
        Http::fake(function (): void {
            throw new \RuntimeException('network down');
        });

        $result = $this->service()->send('+970599000000', 'hello', [
            'provider' => 'hotsms',
            'username' => 'u',
            'password' => 'p',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('network down', (string) $result['response']);
    }
}
