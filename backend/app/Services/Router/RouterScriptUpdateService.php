<?php

declare(strict_types=1);

namespace App\Services\Router;

use App\Enums\RouterCommandStatus;
use App\Enums\RouterCommandVerb;
use App\Models\Router;
use App\Models\RouterCommand;
use App\Repositories\Eloquent\RouterScriptRepository;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Remote script updates. Nothing is pushed on its own: the owner sees that a
 * newer version exists and confirms, which queues one `update-script` command
 * per system script. The router downloads each script from the authenticated
 * script endpoint, verifies the sha256 and keeps the old script on a mismatch.
 */
class RouterScriptUpdateService
{
    /** Script file => system script name; interpreter last so it replaces itself after the others. */
    private const SYSTEM_SCRIPTS = [
        'hooks.rsc' => 'cws-hooks',
        'sync.rsc' => 'cws-sync',
        'snapshot.rsc' => 'cws-snapshot',
        'interpreter.rsc' => 'cws-interpreter',
    ];

    public function __construct(
        private readonly RouterScriptRepository $scripts,
        private readonly RouterCommandService $commands,
        private readonly AuditLogger $audit,
    ) {}

    /** @return array{available: bool, current_version: string|null, latest_version: string, mode: string|null} */
    public function availableFor(Router $router): array
    {
        $current = $router->script_version;
        $latest = $this->scripts->version();

        return [
            'available' => $current !== null && version_compare($current, $latest, '<'),
            'current_version' => $current,
            'latest_version' => $latest,
            'mode' => $router->mode,
        ];
    }

    /**
     * @return list<RouterCommand> the commands queued (none are repeated while an identical one is still open)
     *
     * @throws ValidationException when the router is up to date and a full update was not requested
     */
    public function push(Router $router, bool $full): array
    {
        if (! $full && ! $this->availableFor($router)['available']) {
            throw ValidationException::withMessages(['full' => [__('messages.router_script_up_to_date')]]);
        }

        $queued = DB::transaction(function () use ($router): array {
            $open = $this->openShas($router);
            $queued = [];

            foreach (self::SYSTEM_SCRIPTS as $file => $systemName) {
                $sha = $this->scripts->renderedSha($router, $file);

                if (! isset($open[$systemName.'|'.$sha])) {
                    $queued[] = $this->commands->enqueue($router, RouterCommandVerb::UpdateScript, ['script' => $systemName, 'sha256' => $sha]);
                }
            }

            return $queued;
        });

        $this->audit->log('network.router.script_update_pushed', $router, null, [
            'from_version' => $router->script_version,
            'to_version' => $this->scripts->version(),
            'full' => $full,
            'mode' => $router->mode,
            'commands' => count($queued),
        ]);

        return $queued;
    }

    /** @return array<string, true> "script|sha" of the update commands still waiting for the router */
    private function openShas(Router $router): array
    {
        $open = RouterCommand::query()->withoutWorkspaceScope()
            ->where('router_id', $router->getKey())
            ->where('verb', RouterCommandVerb::UpdateScript->value)
            ->whereIn('status', RouterCommandStatus::openValues())
            ->where('expires_at', '>', now())
            ->get();

        $keys = [];
        foreach ($open as $command) {
            $keys[$command->payload['script'].'|'.$command->payload['sha256']] = true;
        }

        return $keys;
    }
}
