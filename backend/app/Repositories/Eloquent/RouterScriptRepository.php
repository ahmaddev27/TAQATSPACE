<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Router;
use InvalidArgumentException;
use RuntimeException;

/**
 * Reads the versioned RouterOS script templates shipped in resources/routeros.
 * Templates hold only {{PLACEHOLDERS}}; secrets (API key, install token) are
 * injected at generation time and never stored in the files.
 */
class RouterScriptRepository
{
    public const SCRIPTS = [
        'install-precheck.rsc',
        'hooks.rsc',
        'sync.rsc',
        'snapshot.rsc',
        'interpreter.rsc',
    ];

    public function __construct(private readonly string $directory = '')
    {
    }

    public function version(): string
    {
        return (string) $this->versionFile()['version'];
    }

    public function minimumRouterOsVersion(): string
    {
        return (string) $this->versionFile()['min_routeros'];
    }

    /** @return array<string, string> script name => declared sha256 */
    public function declaredHashes(): array
    {
        return $this->versionFile()['files'];
    }

    /** @return array<string, string> script name => sha256 computed from the files on disk */
    public function computedHashes(): array
    {
        $hashes = [];
        foreach (self::SCRIPTS as $name) {
            $hashes[$name] = $this->sha256($name);
        }

        return $hashes;
    }

    /** Line endings are normalised so the hash is identical on every checkout. */
    public function sha256(string $name): string
    {
        return hash('sha256', $this->normalise($this->template($name)));
    }

    public function template(string $name): string
    {
        if (! in_array($name, self::SCRIPTS, true)) {
            throw new InvalidArgumentException("Unknown router script [{$name}].");
        }

        $contents = file_get_contents($this->path($name));

        return $contents === false
            ? throw new RuntimeException("Router script [{$name}] is not readable.")
            : $contents;
    }

    /**
     * @param  array<string, string>  $values  placeholder name => value
     */
    public function render(string $name, array $values): string
    {
        $values += ['SCRIPT_VERSION' => $this->version()];
        $search = array_map(fn (string $key): string => '{{'.$key.'}}', array_keys($values));
        $rendered = str_replace($search, array_values($values), $this->normalise($this->template($name)));

        if (preg_match('/\{\{([A-Z_]+)\}\}/', $rendered, $missing) === 1) {
            throw new InvalidArgumentException("Placeholder [{$missing[1]}] has no value for [{$name}].");
        }

        return $rendered;
    }

    /**
     * Maps a requested name ("hooks", "hooks.rsc" or the system script name
     * "cws-hooks") to a file listed in version.json, or null when it is not one.
     */
    public function resolve(string $requested): ?string
    {
        $stem = preg_replace('/\.rsc$/', '', preg_replace('/^cws-/', '', $requested));
        $name = $stem.'.rsc';

        return in_array($name, self::SCRIPTS, true) && isset($this->declaredHashes()[$name]) ? $name : null;
    }

    /**
     * The script as the router receives it. The API key is deliberately not
     * injected: the router already holds it (memory and flash), and keeping it
     * out makes the output, and so its hash, depend on the router alone.
     */
    public function renderFor(Router $router, string $name): string
    {
        return $this->render($name, [
            'SYNC_URL' => rtrim((string) config('services.router.sync_url'), '/'),
            'ROUTER_CODE' => (string) $router->workspace->code,
            'API_KEY' => '',
            'MODE' => 'full',
        ]);
    }

    /** sha256 of the rendered script: what `update-script` commands (2.8) must send. */
    public function renderedSha(Router $router, string $name): string
    {
        return hash('sha256', $this->renderFor($router, $name));
    }

    /** @return list<string> names whose content no longer matches version.json */
    public function outdatedScripts(): array
    {
        $declared = $this->declaredHashes();

        return array_values(array_filter(
            self::SCRIPTS,
            fn (string $name): bool => ($declared[$name] ?? null) !== $this->sha256($name),
        ));
    }

    /** @return array{version: string, min_routeros: string, files: array<string, string>} */
    private function versionFile(): array
    {
        $decoded = json_decode((string) file_get_contents($this->path('version.json')), true);

        if (! is_array($decoded) || ! isset($decoded['version'], $decoded['min_routeros'], $decoded['files'])) {
            throw new RuntimeException('version.json is missing or malformed.');
        }

        return $decoded;
    }

    private function normalise(string $contents): string
    {
        return str_replace("\r\n", "\n", $contents);
    }

    private function path(string $file): string
    {
        $directory = $this->directory !== '' ? $this->directory : resource_path('routeros');

        return rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$file;
    }
}
