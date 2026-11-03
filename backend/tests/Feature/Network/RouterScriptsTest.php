<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Repositories\Eloquent\RouterScriptRepository;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * RouterOS cannot run in CI, so these are static checks on the versioned
 * templates. Behaviour on a real router is verified on CHR in task 2.12.
 */
class RouterScriptsTest extends TestCase
{
    private RouterScriptRepository $scripts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scripts = new RouterScriptRepository;
    }

    public function test_version_file_hashes_match_the_script_files(): void
    {
        $this->assertSame([], $this->scripts->outdatedScripts(), 'Update resources/routeros/version.json after editing a script.');
        $this->assertSame($this->scripts->computedHashes(), $this->scripts->declaredHashes());
        $this->assertSame('1.0.0', $this->scripts->version());
        $this->assertSame('7.12', $this->scripts->minimumRouterOsVersion());
    }

    public function test_hash_ignores_line_ending_differences(): void
    {
        $dir = sys_get_temp_dir().'/cws-scripts-'.uniqid();
        mkdir($dir);
        foreach (RouterScriptRepository::SCRIPTS as $name) {
            file_put_contents("{$dir}/{$name}", str_replace("\n", "\r\n", $this->scripts->template($name)));
        }
        copy(resource_path('routeros/version.json'), "{$dir}/version.json");

        $this->assertSame([], (new RouterScriptRepository($dir))->outdatedScripts());
    }

    public function test_unknown_script_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->scripts->template('../../.env');
    }

    public function test_scripts_contain_the_core_commands(): void
    {
        $expected = [
            'install-precheck.rsc' => ['/system resource get version', 'check-certificate=yes', ':error'],
            'hooks.rsc' => ['on-login', 'on-logout', 'cwsRecord', 'cwsMaxEvents', 'cwsBootId'],
            'sync.rsc' => ['/tool fetch', 'X-API-Key', 'cws-state.txt', 'seq=', 'cwsLastBatch', 'cwsLastBody'],
            'snapshot.rsc' => ['/ip hotspot active', 'cwsBuildSnapshot', 'cwsNextSnapshotChunk'],
            'interpreter.rsc' => ['CWS-SYNC', 'cwsAllowedVerbs', 'cwsInterpret'],
        ];

        foreach ($expected as $name => $needles) {
            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $this->scripts->template($name), "{$name} lacks {$needle}");
            }
        }
    }

    public function test_templates_carry_placeholders_and_no_secret_values(): void
    {
        foreach (RouterScriptRepository::SCRIPTS as $name) {
            $this->assertDoesNotMatchRegularExpression('/\b[0-9a-f]{32,}\b/', $this->scripts->template($name), "{$name} embeds a secret-looking value");
        }
        $this->assertStringContainsString('{{API_KEY}}', $this->scripts->template('sync.rsc'));
        $this->assertStringContainsString('{{SYNC_URL}}', $this->scripts->template('sync.rsc'));
    }

    public function test_render_injects_values_and_refuses_unresolved_placeholders(): void
    {
        $values = ['SYNC_URL' => 'https://sync.taqat.space', 'ROUTER_CODE' => 'ABCD2345', 'API_KEY' => 'k-test', 'MODE' => 'full'];
        $rendered = $this->scripts->render('sync.rsc', $values);

        $this->assertStringContainsString('https://sync.taqat.space/api/v1/hotspot/ABCD2345/sync', $rendered);
        $this->assertStringContainsString('script version 1.0.0', $rendered);
        $this->assertStringNotContainsString('{{', $rendered);

        $this->expectException(InvalidArgumentException::class);
        $this->scripts->render('sync.rsc', ['SYNC_URL' => 'https://sync.taqat.space']);
    }

    public function test_interpreter_only_knows_the_allowlisted_verbs_and_never_executes_the_response(): void
    {
        $interpreter = $this->scripts->template('interpreter.rsc');
        $verbs = ['upsert-user', 'disable-user', 'remove-active', 'block-mac', 'set-key', 'update-script', 'upsert-profile'];

        preg_match('/:global cwsAllowedVerbs \{(.*?)\}/', $interpreter, $match);
        preg_match_all('/"([a-z-]+)"/', $match[1], $declared);
        $this->assertEqualsCanonicalizing($verbs, $declared[1]);

        foreach (RouterScriptRepository::SCRIPTS as $name) {
            $this->assertDoesNotMatchRegularExpression('/:(execute|parse)\b|\[\s*:parse/', $this->scripts->template($name), "{$name} evaluates dynamic code");
        }
        $this->assertStringContainsString('"not-allowed"', $interpreter);
        $this->assertStringContainsString('cws: command', $interpreter);
    }

    public function test_hotspot_runs_with_shared_users_one_and_a_visitor_profile(): void
    {
        $hooks = $this->scripts->template('hooks.rsc');
        $interpreter = $this->scripts->template('interpreter.rsc');

        $this->assertStringContainsString('/ip hotspot user profile add name=visitor shared-users=1', $hooks);
        foreach ([$hooks, $interpreter] as $script) {
            preg_match_all('/shared-users=(\S+)/', $script, $values);
            $this->assertNotEmpty($values[1]);
            $this->assertSame(['1'], array_values(array_unique($values[1])));
        }
    }

    public function test_poll_only_mode_never_touches_existing_hooks_or_hotspot_objects(): void
    {
        $hooks = $this->scripts->template('hooks.rsc');
        [$pollOnlyBranch, $fullBranch] = explode('} else={', $hooks, 2);

        $this->assertStringContainsString('{{MODE}}', $hooks);
        $this->assertStringContainsString('if ($cwsMode = "poll-only")', $pollOnlyBranch);
        $this->assertDoesNotMatchRegularExpression('/on-login=|on-logout=|profile (add|set)/', substr($pollOnlyBranch, strpos($pollOnlyBranch, 'if ($cwsMode')));
        $this->assertStringContainsString('profile set $p', $fullBranch);

        $interpreter = $this->scripts->template('interpreter.rsc');
        preg_match('/:global cwsPollOnlyVerbs \{(.*?)\}/', $interpreter, $match);
        preg_match_all('/"([a-z-]+)"/', $match[1], $allowedInPollOnly);
        $this->assertEqualsCanonicalizing(['set-key', 'update-script'], $allowedInPollOnly[1]);

        $this->assertStringContainsString('if ($cwsMode = "poll-only") do={ :set snapshotEvery 1 }', $this->scripts->template('sync.rsc'));
    }

    public function test_snapshot_chunks_stay_below_the_four_kilobyte_limit(): void
    {
        preg_match('/:global cwsSnapSize (\d+)/', $this->scripts->template('snapshot.rsc'), $size);

        $this->assertLessThan(4096, (int) $size[1]);
        $this->assertStringContainsString('every 36 cycles', $this->scripts->template('sync.rsc'));
    }
}
