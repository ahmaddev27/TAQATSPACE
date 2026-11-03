<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Models\HotspotEvent;
use App\Models\Router;
use App\Models\RouterSnapshot;
use App\Models\Workspace;
use App\Repositories\Eloquent\RouterScriptRepository;
use App\Services\Hotspot\SyncWireParser;
use App\Support\Router\RouterKeyHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

/**
 * Task 2.15: the router scripts (2.3), the installer (2.2) and the sync
 * endpoint (2.4) must speak one protocol. Everything here is read from the
 * real files, so editing a script or a route without the other side fails.
 */
class RouterProtocolContractTest extends TestCase
{
    use RefreshDatabase;

    private const CODE = 'ABCD2345';

    private Router $router;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $workspace = Workspace::factory()->create(['code' => self::CODE]);
        $this->key = RouterKeyHasher::generate();
        $this->router = Router::factory()->withKey($this->key)->create(['workspace_id' => $workspace->id]);
    }

    private function readFile(string $relative): string
    {
        return (string) file_get_contents(resource_path($relative));
    }

    /** @return array<string, list<string>> label => the lines of the file */
    private function sources(): array
    {
        $sources = ['views/routeros/install.rsc.blade.php' => $this->readFile('views/routeros/install.rsc.blade.php')];

        foreach (RouterScriptRepository::SCRIPTS as $name) {
            $sources["routeros/{$name}"] = $this->readFile("routeros/{$name}");
        }

        return $sources;
    }

    /**
     * Every `/tool fetch` statement of the scripts and the installer, with the
     * URL resolved to a concrete path and the header names it sends.
     *
     * @return list<array{file: string, method: string, path: string, headers: list<string>}>
     */
    private function fetchCalls(): array
    {
        $calls = [];

        foreach ($this->sources() as $file => $text) {
            $text = preg_replace('/\\\\\R\s*/', ' ', $text);
            $text = preg_replace('/"\s*\.\s*\$cwsWorkspace\s*\.\s*"/', self::CODE, $text);
            $text = preg_replace('/"\s*\.\s*\$script\s*\.\s*"/', 'hooks', $text);
            $text = str_replace(['{{ROUTER_CODE}}', '$1'], [self::CODE, 'hooks'], $text);

            $lastPath = '';

            foreach (preg_split('/\R/', $text) as $line) {
                if (! str_starts_with(ltrim($line), '#') && preg_match('#/api/v1/[^\s")]*#', $line, $seen)) {
                    $lastPath = $seen[0];
                }

                if (! str_contains($line, '/tool fetch') || str_starts_with(ltrim($line), '#')) {
                    continue;
                }

                preg_match('#/api/v1/[^\s")]*#', $line, $url);
                preg_match_all('/\bX-[A-Za-z-]+(?=:)/', $line, $headers);

                $calls[] = [
                    'file' => $file,
                    'method' => str_contains($line, 'http-method=post') ? 'POST' : 'GET',
                    'path' => $url[0] ?? $lastPath,
                    'headers' => $headers[0],
                ];
            }
        }

        return $calls;
    }

    private function findRoute(string $method, string $path): ?LaravelRoute
    {
        try {
            return Route::getRoutes()->match(Request::create($path, $method));
        } catch (HttpExceptionInterface) {
            return null;
        }
    }

    /** @return list<string> the lines of the leading `#   X|...` comment documenting a record */
    private function documentedFields(string $script, string $tag): array
    {
        $pattern = $tag === 'E' ? '/\bE\|(seq\|type\|date time\|[^\s"]+)/' : '/^#\s+H\|(\S+)/m';
        preg_match($pattern, $this->readFile("routeros/{$script}"), $match);
        $this->assertNotEmpty($match, "{$script} no longer documents the {$tag} line");

        return explode('|', $match[1]);
    }

    public function test_every_url_and_header_the_scripts_use_resolves_to_a_route_that_accepts_them(): void
    {
        $calls = $this->fetchCalls();

        $this->assertGreaterThanOrEqual(4, count($calls), 'precheck, sync, update-script and the installer must each fetch');
        $this->assertContains('/api/v1/hotspot/ping', array_column($calls, 'path'));

        foreach ($calls as $call) {
            $label = "{$call['file']} {$call['method']} {$call['path']}";
            $route = $this->findRoute($call['method'], $call['path']);

            $this->assertNotNull($route, "{$label} matches no registered route");
            $this->assertNotContains('X-Router-Key', $call['headers'], $label);
            $this->assertSame(
                $call['headers'] !== [],
                in_array('router', $route->gatherMiddleware(), true),
                "{$label} sends ".json_encode($call['headers']).' but the route '.(in_array('router', $route->gatherMiddleware(), true) ? 'needs' : 'does not use').' the router key',
            );
            $this->assertEqualsCanonicalizing($call['headers'] === [] ? [] : ['X-API-Key'], $call['headers'], $label);
        }
    }

    public function test_the_header_documented_in_sync_rsc_is_the_one_the_parser_reads(): void
    {
        $this->assertSame(SyncWireParser::HEADER_FIELDS, $this->documentedFields('sync.rsc', 'H'));
    }

    public function test_sync_rsc_builds_the_header_in_the_documented_field_order(): void
    {
        preg_match('/:local head "H\|([^"\\\\]*)/', $this->readFile('routeros/sync.rsc'), $match);

        $built = array_map(
            static fn (string $field): string => trim($field, '${}'),
            explode('|', $match[1]),
        );

        $this->assertSame(
            ['cwsLastBatch', 'cwsSeq', 'cwsBootId', 'now', 'cwsMode', 'nonce', 'SCRIPT_VERSION', 'serial', 'rosVersion'],
            $built,
        );
        $this->assertCount(count(SyncWireParser::HEADER_FIELDS), $built);
    }

    /** Counts the `|` separated fields of the `:local line "<prefix>...\n"` a script builds, ignoring `$[...]` and `$(...)` substitutions. */
    private function builtFieldCount(string $script, string $prefix): int
    {
        $this->assertSame(1, preg_match('/:local line "'.preg_quote($prefix, '/').'(.*)\\\\n"/', $script, $match), "no :local line starting with {$prefix}");
        $fields = preg_replace(['/\$\[[^\]]*\]/', '/\$\([^)]*\)/'], 'x', $match[1]);

        return substr_count($fields, '|') + 1;
    }

    public function test_event_snapshot_and_ack_lines_built_by_the_scripts_match_the_parser(): void
    {
        $hooks = $this->readFile('routeros/hooks.rsc');
        $documented = $this->documentedFields('sync.rsc', 'E');
        $this->assertSame($documented, $this->documentedFields('hooks.rsc', 'E'));
        $this->assertSame(['seq', 'type', 'date time', 'user', 'mac', 'ip', 'uptime', 'bytes-in', 'bytes-out'], $documented);

        $this->assertSame(count($documented), $this->builtFieldCount($hooks, 'E|'), 'hooks.rsc builds a different number of E fields');
        $this->assertSame(6, $this->builtFieldCount($this->readFile('routeros/snapshot.rsc'), ''), 'snapshot.rsc builds a different number of row fields');

        $this->assertStringContainsString('"S|$cwsSnapId|$index|$cwsSnapTotal\n$chunk"', $this->readFile('routeros/snapshot.rsc'));
        $this->assertStringContainsString('"A|$($fields->1)|$result\n"', $this->readFile('routeros/interpreter.rsc'));
    }

    public function test_a_body_built_field_by_field_from_the_documented_order_parses_back_to_the_same_values(): void
    {
        $header = array_map(static fn (string $name): string => 'v_'.$name, SyncWireParser::HEADER_FIELDS);
        $header[array_search('now', SyncWireParser::HEADER_FIELDS, true)] = 'nov/03/2026 12:30:00';
        $header[array_search('mode', SyncWireParser::HEADER_FIELDS, true)] = 'poll-only';
        $header[array_search('seq', SyncWireParser::HEADER_FIELDS, true)] = '12';

        $parsed = (new SyncWireParser)->parse('H|'.implode('|', $header)."\nE|5|login|nov/03/2026 12:29:50|alice|AA:BB:CC:DD:EE:01|10.0.0.5|1h|10|20\nA|cmd1|sha-mismatch\n");

        foreach (['batch_id', 'boot_id', 'nonce', 'script_version', 'serial', 'routeros_version'] as $name) {
            $this->assertSame('v_'.$name, $parsed[$name]);
        }
        $this->assertSame('poll-only', $parsed['mode']);
        $this->assertSame('5', $parsed['events'][0]['seq']);
        $this->assertSame('alice', $parsed['events'][0]['username']);
        $this->assertSame(['command_id' => 'cmd1', 'status' => 'sha-mismatch'], $parsed['acks'][0]);
    }

    /** @return array{0: string, 1: string} body and the nonce inside it */
    private function routerBody(string $date = 'nov/03/2026'): array
    {
        $body = "H|batch0001|3|boot7|{$date} 12:30:00|full|nonce12abcd|1.0.0|SN1|7.14.3\n"
            ."E|1|login|{$date} 12:29:00|alice|AA:BB:CC:DD:EE:01|10.0.0.5|||\n"
            ."E|2|logout|{$date} 12:29:30|alice|AA:BB:CC:DD:EE:01|10.0.0.5|30s|100|200\n"
            ."S|snap1|0|1\n"
            ."alice|AA:BB:CC:DD:EE:01|10.0.0.5|1m|100|200\n"
            ."A|cmd1|ok\n"
            ."A|cmd2|not-allowed\n";

        return [$body, 'nonce12abcd'];
    }

    private function postText(string $body, ?string $key = null, string $uri = '/api/v1/hotspot/'.self::CODE.'/sync')
    {
        return $this->call('POST', $uri, [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
            'HTTP_X-API-Key' => $key ?? $this->key,
            'HTTP_ACCEPT' => 'text/plain',
        ], $body);
    }

    public function test_a_full_text_plain_sync_is_stored_and_answered_with_the_prefix_the_interpreter_expects(): void
    {
        preg_match('/\(\$header->0\) != "([^"]+)"/', $this->readFile('routeros/interpreter.rsc'), $expected);
        [$body, $nonce] = $this->routerBody();

        $response = $this->postText($body);

        $response->assertOk();
        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
        $header = explode(' ', explode("\n", $response->getContent())[0]);
        $this->assertSame([$expected[1], $nonce], $header);
        $this->assertSame(2, HotspotEvent::query()->where('router_id', $this->router->id)->count());
        $this->assertDatabaseHas('hotspot_events', ['event_uid' => 'boot7-1', 'type' => 'login', 'username' => 'alice']);
        $snapshot = RouterSnapshot::query()->firstOrFail();
        $this->assertSame(1, $snapshot->received_chunks);
        $this->assertSame(['alice|AA:BB:CC:DD:EE:01|10.0.0.5|1m|100|200'], $snapshot->chunks->pluck('payload')->all());
        $this->assertSame('SN1', $this->router->refresh()->serial_number);
        $this->assertSame('7.14.3', $this->router->routeros_version);
    }

    public function test_the_iso_date_format_is_read_too(): void
    {
        [$body] = $this->routerBody('2026-11-03');

        $this->postText($body)->assertOk();

        $this->assertSame(2, HotspotEvent::query()->count());
    }

    public function test_resending_the_same_batch_stores_nothing_new_and_returns_the_identical_reply(): void
    {
        [$body] = $this->routerBody();

        $first = $this->postText($body)->getContent();
        $second = $this->postText($body)->getContent();

        $this->assertSame($first, $second);
        $this->assertSame(2, HotspotEvent::query()->count());
        $this->assertSame(1, RouterSnapshot::query()->count());
    }

    public function test_a_malformed_line_returns_422_and_stores_nothing(): void
    {
        [$body] = $this->routerBody();

        $this->postText($body."E|3|login|not a time|x|||||\n")->assertStatus(422);
        $this->postText("X|garbage\n".$body)->assertStatus(422);

        $this->assertSame(0, HotspotEvent::query()->count());
        $this->assertSame(0, RouterSnapshot::query()->count());
    }

    public function test_a_script_fetched_from_the_route_is_fully_rendered_and_matches_its_rendered_sha(): void
    {
        $scripts = app(RouterScriptRepository::class);

        foreach (['hooks', 'cws-sync', 'interpreter.rsc'] as $name) {
            $response = $this->get('/api/v1/hotspot/'.self::CODE.'/scripts/'.$name, ['X-API-Key' => $this->key]);

            $response->assertOk();
            $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
            $this->assertStringNotContainsString('{{', $response->getContent());
            $file = $scripts->resolve($name);
            $this->assertSame($scripts->renderedSha($this->router, $file), hash('sha256', $response->getContent()));
        }
    }

    public function test_the_script_route_only_serves_listed_scripts_and_requires_the_key(): void
    {
        $headers = ['X-API-Key' => $this->key];

        $this->get('/api/v1/hotspot/'.self::CODE.'/scripts/secrets', $headers)->assertNotFound();
        $this->get('/api/v1/hotspot/'.self::CODE.'/scripts/..%2F.env', $headers)->assertNotFound();
        $this->get('/api/v1/hotspot/'.self::CODE.'/scripts/hooks', ['X-API-Key' => 'wrong'])->assertUnauthorized();
        $this->get('/api/v1/hotspot/'.self::CODE.'/scripts/hooks')->assertUnauthorized();
    }

    public function test_ping_is_public_and_answers_ok(): void
    {
        $response = $this->get('/api/v1/hotspot/ping');

        $response->assertOk();
        $this->assertSame('ok', $response->getContent());
    }
}
