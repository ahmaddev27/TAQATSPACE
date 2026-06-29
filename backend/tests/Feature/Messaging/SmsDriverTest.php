<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Services\Sms\HotSmsDriver;
use App\Services\Sms\MtcSmsDriver;
use App\Services\Sms\SmsService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The MTC + HotSMS drivers must hit the real provider endpoints with the right
 * (URL-encoded) parameters, mapped from the stored messaging-settings fields.
 */
class SmsDriverTest extends TestCase
{
    public function test_mtc_driver_calls_the_real_endpoint_with_mapped_params(): void
    {
        Http::fake(['int.mtcsms.com/*' => Http::response('OK', 200)]);

        $result = (new MtcSmsDriver([
            'username' => 'taqat-gaza',
            'password' => 'secret',
            'sender' => 'TAQAT-GAZA',
        ]))->send('970599000000', 'مرحبا');

        $this->assertTrue($result['ok']);
        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://int.mtcsms.com/sendsms.aspx')
                && $request->method() === 'GET'
                && $request['username'] === 'taqat-gaza'
                && $request['password'] === 'secret'
                && $request['from'] === 'TAQAT-GAZA'
                && $request['to'] === '970599000000'
                && $request['msg'] === 'مرحبا'
                && (string) $request['type'] === '0';
        });
    }

    public function test_hotsms_driver_prefers_api_token(): void
    {
        Http::fake(['hotsms.ps/*' => Http::response('OK', 200)]);

        (new HotSmsDriver([
            'api_key' => 'tok_123',
            'sender' => 'SMS',
        ]))->send('970599000000', 'Welcome');

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'http://hotsms.ps/sendbulksms.php')
                && $request['api_token'] === 'tok_123'
                && $request['sender'] === 'SMS'
                && $request['mobile'] === '970599000000'
                && $request['text'] === 'Welcome'
                && ! isset($request['user_name']);
        });
    }

    public function test_hotsms_driver_falls_back_to_username_password(): void
    {
        Http::fake(['hotsms.ps/*' => Http::response('OK', 200)]);

        (new HotSmsDriver([
            'username' => 'test',
            'password' => 'pass',
            'sender' => 'SMS',
        ]))->send('970599000000', 'Hi');

        Http::assertSent(function ($request) {
            return $request['user_name'] === 'test'
                && $request['user_pass'] === 'pass'
                && ! isset($request['api_token']);
        });
    }

    public function test_driver_reports_failure_on_error_response(): void
    {
        Http::fake(['int.mtcsms.com/*' => Http::response('Not found', 404)]);

        $result = (new MtcSmsDriver(['username' => 'u', 'password' => 'p', 'sender' => 's']))
            ->send('970599000000', 'x');

        $this->assertFalse($result['ok']);
    }

    public function test_sms_service_routes_to_the_configured_provider(): void
    {
        Http::fake(['int.mtcsms.com/*' => Http::response('OK', 200)]);

        $result = app(SmsService::class)->send('970599000000', 'hi', [
            'provider' => 'mtcsms',
            'username' => 'u',
            'password' => 'p',
            'sender' => 's',
        ]);

        $this->assertTrue($result['ok']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'int.mtcsms.com'));
    }
}
