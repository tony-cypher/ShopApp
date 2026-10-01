<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root path redirects visitors to the React storefront.
     */
    public function test_the_application_redirects_root_to_the_storefront(): void
    {
        $response = $this->get('/');

        $response->assertRedirect();
    }
}
