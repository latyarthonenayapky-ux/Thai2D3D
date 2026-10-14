<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Laravel's global ValidatePostSize middleware compares CONTENT_LENGTH against
 * PHP's own post_max_size and answers 413 before any controller runs. Without
 * an errors/413 view that page was Symfony's generic "Oops! An Error Occurred",
 * which told the Admin nothing about the APK they had just published.
 */
class UploadTooLargeResponseTest extends TestCase
{
    private string $originalPostLimit = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalPostLimit = (string) ini_get('post_max_size');

        ini_set('post_max_size', '1M');
    }

    protected function tearDown(): void
    {
        ini_set('post_max_size', $this->originalPostLimit);

        parent::tearDown();
    }

    public function test_an_over_sized_upload_is_refused_with_a_readable_page(): void
    {
        $this->withServerVariables(['CONTENT_LENGTH' => 40 * 1024 * 1024])
            ->post(route('admin.settings.mobile-app.store'))
            ->assertStatus(413)
            ->assertSee('That file is too large for this server', false)
            ->assertDontSee('Oops! An Error Occurred', false);
    }

    public function test_a_post_under_the_limit_still_reaches_the_application(): void
    {
        $this->post(route('admin.settings.mobile-app.store'))
            ->assertRedirect(route('login'));
    }
}
