<?php

namespace Tests\Feature;

use App\Services\ExportService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportControllerTest extends TestCase
{
    private string $key;
    private array $paths;

    protected function setUp(): void
    {
        parent::setUp();

        $this->key = config('auth.request_auth_key');
        $this->paths = ExportService::getOrderCostPaths('test-');

        foreach ($this->paths as $path) {
            Storage::put($path, '');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            Storage::delete($path);
        }

        parent::tearDown();
    }

    /**
     * A basic feature test example.
     */
    public function test_download_success(): void
    {
        foreach ($this->paths as $path) {
            $file = basename($path);
            $this->get('export/' . $file . '?key=' . $this->key)->assertSuccessful();
        }
    }
}
