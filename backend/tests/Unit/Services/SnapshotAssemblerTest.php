<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Router;
use App\Models\RouterSnapshot;
use App\Models\RouterSnapshotChunk;
use App\Services\Network\SnapshotAssembler;
use App\Support\Router\RouterOsDuration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SnapshotAssemblerTest extends TestCase
{
    use RefreshDatabase;

    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->router = Router::factory()->create();
    }

    /** @param array<int, string> $chunks chunk_index => payload, inserted in the given order */
    private function snapshot(array $chunks, int $total, bool $complete = true): RouterSnapshot
    {
        $snapshot = RouterSnapshot::create([
            'router_id' => $this->router->id,
            'snapshot_id' => 'snap-'.uniqid(),
            'total_chunks' => $total,
            'received_chunks' => count($chunks),
            'taken_at' => now(),
            'completed_at' => $complete ? now() : null,
        ]);

        foreach ($chunks as $index => $payload) {
            RouterSnapshotChunk::create(['router_snapshot_id' => $snapshot->id, 'chunk_index' => $index, 'payload' => $payload]);
        }

        return $snapshot;
    }

    public function test_chunks_arriving_in_random_order_are_assembled_by_index(): void
    {
        $snapshot = $this->snapshot([
            2 => 'u3|AA:AA:AA:AA:AA:03|10.0.0.3|3s|3|3',
            0 => "u1|AA:AA:AA:AA:AA:01|10.0.0.1|1m|1|1\nu2|aa:aa:aa:aa:aa:02|10.0.0.2|1h2m3s|20|30",
            1 => '',
        ], total: 3);

        $assembled = app(SnapshotAssembler::class)->assemble($snapshot);

        $this->assertNotNull($assembled);
        $this->assertSame(
            ['AA:AA:AA:AA:AA:01', 'AA:AA:AA:AA:AA:02', 'AA:AA:AA:AA:AA:03'],
            array_map(fn ($row) => $row->mac, $assembled->rows),
        );
        $this->assertSame(3723, $assembled->rows[1]->uptimeSeconds);
        $this->assertSame(30, $assembled->rows[1]->bytesOut);
        $this->assertSame($this->router->workspace_id, $assembled->workspaceId);
        $this->assertNotNull($snapshot->fresh()->assembled_at);
    }

    public function test_snapshot_is_assembled_exactly_once(): void
    {
        $snapshot = $this->snapshot([0 => 'u1|AA:AA:AA:AA:AA:01|10.0.0.1|1s|1|1'], total: 1);
        $assembler = app(SnapshotAssembler::class);

        $this->assertNotNull($assembler->assemble($snapshot));
        $this->assertNull($assembler->assemble($snapshot->fresh()));
    }

    public function test_missing_chunk_is_not_assembled_or_stamped(): void
    {
        $snapshot = $this->snapshot([0 => 'u1|AA:AA:AA:AA:AA:01|10.0.0.1|1s|1|1', 2 => ''], total: 3);

        $this->assertNull(app(SnapshotAssembler::class)->assemble($snapshot));
        $this->assertNull($snapshot->fresh()->assembled_at);
    }

    public function test_malformed_rows_are_skipped(): void
    {
        $snapshot = $this->snapshot([0 => "garbage\nu1||10.0.0.1|1s|1|1\nu2|AA:AA:AA:AA:AA:02|10.0.0.2|1s|x|2"], total: 1);

        $rows = app(SnapshotAssembler::class)->assemble($snapshot)->rows;

        $this->assertCount(1, $rows);
        $this->assertSame(0, $rows[0]->bytesIn);
        $this->assertSame(2, $rows[0]->bytesOut);
    }

    public function test_router_os_durations_are_converted_to_seconds(): void
    {
        $this->assertSame(0, RouterOsDuration::toSeconds(''));
        $this->assertSame(45, RouterOsDuration::toSeconds('45s'));
        $this->assertSame(694861, RouterOsDuration::toSeconds('1w1d1h1m1s'));
        $this->assertSame(3723, RouterOsDuration::toSeconds('01:02:03'));
        $this->assertSame(90123, RouterOsDuration::toSeconds('1d 01:02:03'));
    }
}
