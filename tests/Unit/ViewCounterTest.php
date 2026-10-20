<?php

namespace Tests\Unit;

use App\Services\ViewCounter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class ViewCounterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['app.key' => 'base64:test-view-counter-secret']);
    }

    public function test_it_counts_a_fingerprint_only_once_per_day(): void
    {
        $model = new class extends Model
        {
            public int $increments = 0;

            public function incrementerVues(): void
            {
                $this->increments++;
            }
        };
        $model->id = 42;
        $model->exists = true;

        $request = Request::create('/article', server: [
            'REMOTE_ADDR' => '192.0.2.10',
            'HTTP_USER_AGENT' => 'SeptentrionalTest/1.0',
        ]);
        $counter = app(ViewCounter::class);

        $counter->increment($model, $request, 'article');
        $counter->increment($model, $request, 'article');

        $this->assertSame(1, $model->increments);
    }

    public function test_it_releases_the_fingerprint_when_the_increment_fails(): void
    {
        $model = new class extends Model
        {
            public int $attempts = 0;

            public function incrementerVues(): void
            {
                $this->attempts++;

                if ($this->attempts === 1) {
                    throw new RuntimeException('database unavailable');
                }
            }
        };
        $model->id = 7;
        $model->exists = true;

        $request = Request::create('/video', server: [
            'REMOTE_ADDR' => '192.0.2.20',
            'HTTP_USER_AGENT' => 'SeptentrionalTest/1.0',
        ]);
        $counter = app(ViewCounter::class);

        try {
            $counter->increment($model, $request, 'video');
            $this->fail('The simulated failure was not raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('database unavailable', $exception->getMessage());
        }

        $counter->increment($model, $request, 'video');

        $this->assertSame(2, $model->attempts);
    }
}
