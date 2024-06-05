<?php

namespace Tests\Feature;

use App\Services\PfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PfServiceTest extends TestCase
{
    private PfService $pfService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pfService = app('App\Services\PfService');
    }

    /**
     * A basic feature test example.
     */
    public function test_process_update_autopilot_accruals_from_google_sheets(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
