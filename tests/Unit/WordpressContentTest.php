<?php

namespace Tests\Unit;

use App\Services\Wordpress\WordpressContent;
use App\Services\Wordpress\WordpressImporter;
use Illuminate\Foundation\Testing\TestCase;
use InvalidArgumentException;

class WordpressContentTest extends TestCase
{
    public function test_gutenberg_html_is_preserved_without_executable_content(): void
    {
        $content = new WordpressContent;
        $html = $content->html('<!-- wp:paragraph --><p>Actualité <strong>du Togo</strong></p><!-- /wp:paragraph --><script>alert(1)</script><img src="https://example.org/photo.jpg" onerror="alert(2)">');
        $this->assertStringContainsString('<strong>du Togo</strong>', $html);
        $this->assertStringNotContainsString('wp:paragraph', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    public function test_future_and_password_protected_posts_keep_their_visibility(): void
    {
        $content = new WordpressContent;
        $this->assertSame('publie', $content->status('future'));
        $this->assertSame('brouillon', $content->status('publish', true));
        $this->assertSame('brouillon', $content->status('private'));
        $this->assertSame('archive', $content->status('trash'));
    }

    public function test_zero_wordpress_dates_are_not_converted_into_invalid_timestamps(): void
    {
        $content = new WordpressContent;
        $this->assertNull($content->date('0000-00-00 00:00:00'));
        $this->assertSame('2026-10-14 12:00:00', $content->date('0000-00-00 00:00:00', '2026-10-14 12:00:00'));
        $this->assertSame('2026-10-14 10:00:00', $content->date('2026-10-14 10:00:00', '2026-10-14 12:00:00'));
    }

    public function test_serialized_plugin_data_cannot_instantiate_objects(): void
    {
        $content = new WordpressContent;
        $this->assertSame(['guid' => 'https://example.org/book.pdf'], $content->array(serialize(['guid' => 'https://example.org/book.pdf'])));
        $this->assertSame([], $content->array(serialize(['nested' => new \stdClass])));
        $this->assertSame([], $content->array('not serialized'));
    }

    public function test_external_urls_are_http_only_and_never_include_credentials(): void
    {
        $content = new WordpressContent;
        $site = 'https://www.septentrional.tg';
        $this->assertNull($content->url('javascript:alert(1)', $site));
        $this->assertNull($content->url('https://user:pass@example.org/file', $site));
        $this->assertSame($site.'/file.pdf', $content->url('/file.pdf', $site));
    }

    public function test_invalid_table_prefix_is_rejected_before_any_connection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new WordpressImporter(new WordpressContent))->configure('wordpress', 'wp_; DROP TABLE users;');
    }

    public function test_default_target_connection_cannot_be_used_as_source(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new WordpressImporter(new WordpressContent))->configure(config('database.default'), 'wp4e_');
    }
}
