<?php

namespace Tests\Unit;

use Mews\Purifier\Facades\Purifier;
use Tests\TestCase;

class EditorialPurifierTest extends TestCase
{
    public function test_editorial_markup_survives_purification_without_unsafe_content(): void
    {
        $html = <<<'HTML'
<script>alert(1)</script>
<h2 style="text-align:center;line-height:2;margin-bottom:1.5rem">Titre</h2>
<p style="text-align:justify"><span style="color:#3b82f6;font-family:Inter">Texte</span></p>
<mark data-color="#fef08a" style="background-color:#fef08a">Important</mark>
<a href="mailto:redaction@example.com" target="_blank" rel="noopener noreferrer">Écrire</a>
<a href="javascript:alert(1)">Dangereux</a>
<img src="https://media.example/image.jpg" width="50%" data-align="left" loading="lazy" decoding="async">
<div data-video-wrapper="" data-src="https://media.example/video.mp4" data-width="50%" data-align="right">
  <video src="https://media.example/video.mp4" controls preload="metadata"></video>
</div>
<div data-youtube-wrapper="" data-src="https://youtu.be/abcdefgh" data-width="75%" data-align="center">
  <iframe src="https://www.youtube.com/embed/abcdefgh" allowfullscreen loading="lazy"
          sandbox="allow-scripts allow-same-origin allow-presentation"
          referrerpolicy="strict-origin-when-cross-origin"></iframe>
</div>
HTML;

        $clean = Purifier::clean($html);

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringContainsString('style="text-align:center;line-height:2;margin-bottom:1.5rem;"', $clean);
        $this->assertStringContainsString('data-color="#fef08a"', $clean);
        $this->assertStringContainsString('href="mailto:redaction@example.com"', $clean);
        $this->assertStringContainsString('loading="lazy"', $clean);
        $this->assertStringContainsString('decoding="async"', $clean);
        $this->assertStringContainsString('data-video-wrapper=""', $clean);
        $this->assertStringContainsString('data-src="https://media.example/video.mp4"', $clean);
        $this->assertStringContainsString('data-width="50%"', $clean);
        $this->assertStringContainsString('data-align="right"', $clean);
        $this->assertStringContainsString('data-youtube-wrapper=""', $clean);
        $this->assertStringContainsString('https://www.youtube.com/embed/abcdefgh', $clean);
        $this->assertStringContainsString('sandbox="allow-scripts allow-same-origin allow-presentation"', $clean);
        $this->assertStringContainsString('referrerpolicy="strict-origin-when-cross-origin"', $clean);
    }

    public function test_legacy_media_flex_alignment_is_migrated_before_purification(): void
    {
        $html = <<<'HTML'
<div data-video-wrapper="" style="display:flex; justify-content:flex-end">
  <video src="https://media.example/legacy.mp4" style="width:50%"></video>
</div>
<div data-youtube-wrapper="" style="display:flex; justify-content:flex-start">
  <iframe src="https://www.youtube.com/embed/abcdefgh" style="width:75%"></iframe>
</div>
<img src="https://media.example/legacy.jpg" class="alignright size-large" style="width:50%">
HTML;

        $clean = Purifier::clean($html);

        $this->assertStringContainsString('data-video-wrapper=""', $clean);
        $this->assertMatchesRegularExpression(
            '/<img(?=[^>]*legacy\.jpg)(?=[^>]*data-align="right")[^>]*>/',
            $clean
        );
        $this->assertStringContainsString('data-youtube-wrapper=""', $clean);
        $this->assertStringContainsString('data-align="left"', $clean);
        $this->assertStringContainsString('style="width:50%;"', $clean);
        $this->assertStringNotContainsString('alignright', $clean);
        $this->assertStringNotContainsString('justify-content', $clean);
        $this->assertStringNotContainsString('display:', $clean);
    }

    public function test_table_column_widths_are_bounded_and_canonicalized(): void
    {
        $tooManyWidths = implode(',', array_fill(0, 33, '100'));
        $html = <<<HTML
<table>
  <tr>
    <th colspan="2" colwidth="120, 240">Conservé</th>
    <td colwidth="100px">Malformé</td>
    <td colwidth="4097">Démesuré</td>
    <td colwidth="{$tooManyWidths}">Trop long</td>
  </tr>
</table>
HTML;

        $clean = Purifier::clean($html);

        $this->assertStringContainsString('colwidth="120,240"', $clean);
        $this->assertStringNotContainsString('colwidth="100px"', $clean);
        $this->assertStringNotContainsString('colwidth="4097"', $clean);
        $this->assertStringNotContainsString($tooManyWidths, $clean);
    }
}
