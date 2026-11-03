<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\WorkspaceResetStep;
use App\Models\Workspace;
use App\Services\Workspace\Reset\WorkspaceResetter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class WorkspaceResetterTest extends TestCase
{
    private function step(string $key, array &$log, array $paths = []): WorkspaceResetStep
    {
        return new class($key, $log, $paths) implements WorkspaceResetStep
        {
            public function __construct(private string $key, private array &$log, private array $paths) {}

            public function key(): string
            {
                return $this->key;
            }

            public function reset(Workspace $workspace): void
            {
                $this->log[] = $this->key;
            }

            public function filePaths(Workspace $workspace): array
            {
                return $this->paths;
            }
        };
    }

    public function test_steps_run_in_registration_order_and_file_paths_are_merged(): void
    {
        $log = [];
        $resetter = new WorkspaceResetter([
            $this->step('a', $log, ['x.jpg', 'y.jpg']),
            $this->step('b', $log, ['y.jpg', 'z.jpg']),
        ]);

        $paths = $resetter->reset(new Workspace);

        $this->assertSame(['a', 'b'], $log);
        $this->assertSame(['a', 'b'], $resetter->keys());
        $this->assertSame(['x.jpg', 'y.jpg', 'z.jpg'], $paths);
    }

    public function test_registering_an_existing_key_replaces_the_step(): void
    {
        $first = [];
        $second = [];
        $resetter = new WorkspaceResetter([$this->step('a', $first)]);
        $resetter->register($this->step('a', $second));

        $resetter->reset(new Workspace);

        $this->assertSame([], $first);
        $this->assertSame(['a'], $second);
    }

    public function test_an_empty_key_is_rejected(): void
    {
        $log = [];
        $this->expectException(InvalidArgumentException::class);

        new WorkspaceResetter([$this->step('', $log)]);
    }
}
