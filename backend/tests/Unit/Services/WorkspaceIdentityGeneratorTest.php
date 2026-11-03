<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Workspace;
use App\Services\WorkspaceIdentityGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceIdentityGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function generator(): WorkspaceIdentityGenerator
    {
        return app(WorkspaceIdentityGenerator::class);
    }

    public function test_code_is_eight_base32_characters(): void
    {
        $code = $this->generator()->generateCode();

        $this->assertSame(8, strlen($code));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{8}$/', $code);
    }

    public function test_codes_are_unique_across_many_generations(): void
    {
        $codes = [];
        for ($i = 0; $i < 200; $i++) {
            $codes[] = $this->generator()->generateCode();
        }

        $this->assertCount(200, array_unique($codes));
    }

    public function test_slug_is_ascii_and_url_safe(): void
    {
        $this->assertSame('gaza-hub-co-2026', $this->generator()->generateSlug('Gaza Hub, Co. 2026'));
    }

    public function test_slug_transliterates_arabic_names_to_a_non_empty_ascii_slug(): void
    {
        $slug = $this->generator()->generateSlug('مساحة طاقات');

        $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug);
    }

    public function test_slug_falls_back_when_the_name_has_no_usable_characters(): void
    {
        $this->assertSame('workspace', $this->generator()->generateSlug('!!!'));
    }

    public function test_identical_names_get_distinct_slugs(): void
    {
        $first = Workspace::factory()->create(['name' => 'Same Name']);
        $second = Workspace::factory()->create(['name' => 'Same Name']);
        $third = Workspace::factory()->create(['name' => 'Same Name']);

        $this->assertSame(['same-name', 'same-name-2', 'same-name-3'], [$first->slug, $second->slug, $third->slug]);
    }

    public function test_slug_collision_counts_soft_deleted_workspaces(): void
    {
        Workspace::factory()->create(['name' => 'Gone'])->delete();

        $this->assertSame('gone-2', $this->generator()->generateSlug('Gone'));
    }
}
