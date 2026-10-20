<?php

namespace App\Services\Wordpress;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

class WordpressContent
{
    public function html(string $content): string
    {
        // Gutenberg stocke du HTML entre ses commentaires. Aucun shortcode ou
        // code de plugin n'est exécuté pendant la migration.
        $content = preg_replace('/<!--\s*\/?wp:.*?-->/s', '', $content) ?? $content;
        $content = preg_replace('/\[caption[^\]]*\](.*?)\[\/caption\]/s', '<figure>$1</figure>', $content) ?? $content;
        $content = preg_replace('/\[(?:embed|video|audio)[^\]]*\](.*?)\[\/(?:embed|video|audio)\]/s', '$1', $content) ?? $content;

        return Purifier::clean($content);
    }

    public function date(?string $gmt, ?string $local = null): ?string
    {
        $value = $gmt && ! str_starts_with($gmt, '0000-') ? $gmt : $local;
        if (! $value || str_starts_with($value, '0000-')) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, 'UTC')->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    public function status(string $status, bool $protected = false): string
    {
        if ($protected) {
            return 'brouillon';
        }

        return match ($status) {
            'publish', 'future' => 'publie',
            'trash' => 'archive',
            default => 'brouillon',
        };
    }

    public function text(string $value, int $length = 255): string
    {
        return Str::limit(trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')), $length, '');
    }

    public function url(?string $value, string $siteUrl): ?string
    {
        if (! $value) {
            return null;
        }
        $value = trim($value);
        if (str_starts_with($value, '/')) {
            $value = rtrim($siteUrl, '/').$value;
        }
        $parts = parse_url($value);

        return $parts && in_array(strtolower($parts['scheme'] ?? ''), ['https', 'http'], true)
            && ! empty($parts['host']) && ! isset($parts['user']) && ! isset($parts['pass']) ? $value : null;
    }

    public function array(string $value): array
    {
        if (strlen($value) > 2_000_000) {
            return [];
        }
        // Les plugins WordPress utilisent la sérialisation PHP. Les objets
        // sont interdits, y compris les objets imbriqués.
        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            $decoded = @unserialize($value, ['allowed_classes' => false, 'max_depth' => 32]);
        }
        if (! is_array($decoded)) {
            return [];
        }
        $valid = function (mixed $item) use (&$valid): bool {
            if (is_array($item)) {
                foreach ($item as $child) {
                    if (! $valid($child)) {
                        return false;
                    }
                }

                return true;
            }

            return is_scalar($item) || $item === null;
        };

        return $valid($decoded) ? $decoded : [];
    }
}
