<?php

namespace Tests\Feature;

use Tests\TestCase;

class DirectAccessProtectionTest extends TestCase
{
    public function test_it_blocks_direct_requests_to_index_php_with_403(): void
    {
        $response = $this->call('GET', '/index.php');

        $response->assertStatus(403);
    }

    public function test_it_allows_standard_entry_to_root(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }
}
