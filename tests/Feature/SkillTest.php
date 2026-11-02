<?php

namespace Tests\Feature;

use App\Models\Skill;
use App\Models\User;
use App\Services\ClaudeOutreachAi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class SkillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_skills_can_be_added_edited_and_removed(): void
    {
        $this->get(route('skills.index'))->assertOk()->assertSee('No skills yet');

        $this->post(route('skills.store'), ['name' => 'Laravel / PHP', 'details' => '6 years, 40+ projects'])
            ->assertSessionHas('success');
        $skill = Skill::sole();

        $this->get(route('skills.index'))->assertSee('Laravel / PHP')->assertSee('6 years, 40+ projects');

        $this->put(route('skills.update', $skill), ['name' => 'Laravel', 'details' => ''])->assertSessionHas('success');
        $this->assertSame('Laravel', $skill->fresh()->name);
        $this->assertNull($skill->fresh()->details);

        $this->put(route('skills.update', $skill), ['name' => ''])->assertSessionHasErrors('name', errorBag: "skill{$skill->id}");

        $this->delete(route('skills.destroy', $skill))->assertSessionHas('success');
        $this->assertSame(0, Skill::count());
    }

    public function test_skills_are_given_to_the_ai_when_writing_emails(): void
    {
        $prompt = fn () => (new ReflectionMethod(ClaudeOutreachAi::class, 'draftSystemPrompt'))->invoke(new ClaudeOutreachAi);

        $this->assertStringNotContainsString("team's skills", $prompt());

        Skill::create(['name' => 'Flutter apps', 'details' => '15 apps on Play Store and App Store']);
        Skill::create(['name' => 'Laravel / PHP']);

        $this->assertStringContainsString("- Flutter apps: 15 apps on Play Store and App Store\n- Laravel / PHP", $prompt());
        $this->assertStringContainsString('Never claim a skill', $prompt());
    }
}
