<?php

namespace Tests\Feature;

use Tests\TestCase;

class SaleInputParserTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
