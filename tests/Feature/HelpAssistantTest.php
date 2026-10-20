<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Assistant\DocIndex;
use App\Services\Assistant\LlmClient;
use App\Services\InitializeAdminBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The help drawer: offline guide lookup first, an optional model on top.
 *
 * A hosted install with no API key must still answer, so the model path is only
 * ever exercised through a faked HTTP client.
 */
class HelpAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_questions_in_either_language_match_the_right_guide(): void
    {
        $index = new DocIndex();

        $this->assertSame([], $index->search(''));
        $this->assertSame([], $index->search('zzzz qqqq nonexistent'));
        $this->assertContains('TROUBLESHOOTING_MM.md', array_column($index->search('413 request entity too large'), 'doc'));
        $this->assertContains('LICENCE_MM.md', array_column($index->search('licence expired read only renewal'), 'doc'));
        $this->assertContains('AMOUNT_LIMITS_MM.md', array_column($index->search('amount limit warn'), 'doc'));
        $this->assertNotEmpty($index->search('စကားဝှက် မေ့နေသည်'));
    }

    public function test_internal_build_guides_are_not_offered_to_operators(): void
    {
        $docs = config('help.docs');

        $this->assertNotContains('DEVELOPMENT_MM.md', $docs);
        $this->assertNotContains('RELEASE_BUILD_MM.md', $docs);

        foreach ($docs as $file) {
            $this->assertFileExists(base_path('docs/'.$file));
        }
    }

    public function test_the_hosted_drawer_answers_from_the_guide_without_any_model_key(): void
    {
        Http::fake();
        $this->assertFalse((new LlmClient)->configured());

        $response = $this->actingAs($this->businessAdmin())->postJson(route('help.assistant'), [
            'question' => 'agent password မေ့နေသည် ဘယ်လိုပြန်ရမလဲ',
            'page' => 'admin/users',
        ]);

        $response->assertOk()->assertJsonStructure(['source', 'answer', 'references']);
        $this->assertSame('docs', $response->json('source'));
        $this->assertNotEmpty($response->json('references'));
        $this->assertStringContainsString('▸', $response->json('answer'));
        $this->assertSame(0, count(Http::recorded()));
    }

    public function test_an_unmatched_question_says_so_instead_of_guessing(): void
    {
        $response = $this->actingAs($this->businessAdmin())
            ->postJson(route('help.assistant'), ['question' => 'zzzz qqqx nonexistent phrase']);

        $response->assertOk()->assertJsonPath('source', 'none');
        $this->assertSame([], $response->json('references'));
        $this->assertNotSame('', $response->json('answer'));
    }

    public function test_a_configured_model_rewrites_the_guide_excerpt_it_was_given(): void
    {
        $this->configureModel();
        Http::fake([
            '*' => Http::response(['content' => [['type' => 'text', 'text' => '1. Open Settings and reset the password.']]], 200),
        ]);

        $response = $this->actingAs($this->businessAdmin())
            ->postJson(route('help.assistant'), ['question' => 'agent password မေ့နေသည်']);

        $response->assertOk()->assertJsonPath('source', 'model');
        $this->assertSame('1. Open Settings and reset the password.', $response->json('answer'));

        $payload = Http::recorded()[0][0];
        $this->assertStringContainsString('Guide excerpts:', $payload['messages'][0]['content']);
        $this->assertStringContainsString('Winning Yields Always', $payload['system']);
    }

    public function test_the_openai_shape_is_also_understood(): void
    {
        $this->configureModel('openai');
        Http::fake([
            '*' => Http::response(['choices' => [['message' => ['content' => 'Open the round page first.']]]], 200),
        ]);

        $this->actingAs($this->businessAdmin())
            ->postJson(route('help.assistant'), ['question' => 'round close'])
            ->assertOk()
            ->assertJsonPath('source', 'model')
            ->assertJsonPath('answer', 'Open the round page first.');
    }

    public function test_a_refusal_from_the_model_falls_back_to_the_guide(): void
    {
        $this->configureModel();
        Http::fake(['*' => Http::response('upstream exploded', 500)]);

        $this->actingAs($this->businessAdmin())
            ->postJson(route('help.assistant'), ['question' => 'round close settlement'])
            ->assertOk()
            ->assertJsonPath('source', 'docs');
    }

    public function test_one_account_cannot_drain_the_model_budget(): void
    {
        $this->configureModel();
        config(['help.llm.daily_limit' => 1]);
        Http::fake([
            '*' => Http::response(['content' => [['type' => 'text', 'text' => 'Open Settings.']]], 200),
        ]);
        $admin = $this->businessAdmin();

        $this->actingAs($admin)->postJson(route('help.assistant'), ['question' => 'round close'])->assertJsonPath('source', 'model');
        $this->actingAs($admin)->postJson(route('help.assistant'), ['question' => 'round close'])->assertJsonPath('source', 'docs');
        $this->assertSame(1, count(Http::recorded()));
    }

    public function test_an_over_long_question_is_refused(): void
    {
        $this->actingAs($this->businessAdmin())
            ->postJson(route('help.assistant'), ['question' => str_repeat('round ', 100)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('question');
    }

    public function test_a_desktop_copy_has_no_assistant_at_all(): void
    {
        config(['app.desktop_mode' => true]);
        $admin = $this->businessAdmin();

        // The licence gate answers first on an unlicensed desktop copy; either
        // way there is nothing to ask. A signed licence would reach the
        // controller, which aborts 404 for the same reason.
        $this->actingAs($admin)->postJson(route('help.assistant'), ['question' => 'round close'])->assertStatus(423);
        $this->get(route('dashboard'))->assertOk()->assertDontSee('id="help-drawer"', false);
    }

    public function test_the_drawer_is_offered_on_the_hosted_install(): void
    {
        $this->actingAs($this->businessAdmin())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="help-drawer"', false)
            ->assertSee(__('app.help.title'));
    }

    private function configureModel(string $provider = 'anthropic'): void
    {
        config([
            'help.llm.provider' => $provider,
            'help.llm.api_key' => 'test-key-not-a-secret',
            'help.llm.model' => 'test-model',
        ]);
    }

    private function businessAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($admin);

        return $admin;
    }
}
