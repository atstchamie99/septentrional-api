<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use HTMLPurifier_Filter;

final class LegacyMediaAlignmentFilter extends HTMLPurifier_Filter
{
    public $name = 'LegacyMediaAlignment';

    public static function __set_state(array $properties): self
    {
        return new self;
    }

    /**
     * Convertit l'ancien style flex des wrappers média en data-align avant
     * qu'HTMLPurifier ne retire display/justify-content, non supportés.
     */
    public function preFilter($html, $config, $context)
    {
        if (
            ! is_string($html)
            || (
                ! str_contains($html, 'data-video-wrapper')
                && ! str_contains($html, 'data-youtube-wrapper')
                && ! preg_match('/\balign(?:left|right|center)\b/i', $html)
            )
        ) {
            return $html;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrorMode = libxml_use_internal_errors(true);
        $rootId = '__septentrional_editorial_root__';

        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8"><div id="'.$rootId.'">'.$html.'</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );

            if (! $loaded) {
                return $html;
            }

            $xpath = new DOMXPath($document);
            $wrappers = $xpath->query(
                '//*[@data-video-wrapper or @data-youtube-wrapper]'
            );

            if ($wrappers !== false) {
                foreach ($wrappers as $wrapper) {
                    if (! $wrapper instanceof DOMElement) {
                        continue;
                    }

                    $currentAlign = trim($wrapper->getAttribute('data-align'));
                    if (in_array($currentAlign, ['left', 'center', 'right'], true)) {
                        continue;
                    }

                    $legacyAlign = $this->legacyAlign($wrapper->getAttribute('style'));
                    if ($legacyAlign) {
                        $wrapper->setAttribute('data-align', $legacyAlign);
                    }
                }
            }

            $images = $xpath->query('//img');
            if ($images !== false) {
                foreach ($images as $image) {
                    if (! $image instanceof DOMElement) {
                        continue;
                    }

                    $currentAlign = trim($image->getAttribute('data-align'));
                    if (in_array($currentAlign, ['left', 'center', 'right'], true)) {
                        continue;
                    }

                    $legacyAlign = $this->legacyImageAlign($image->getAttribute('class'));
                    if ($legacyAlign) {
                        $image->setAttribute('data-align', $legacyAlign);
                    }
                }
            }

            $root = $document->getElementById($rootId);
            if (! $root) {
                return $html;
            }

            $result = '';
            foreach ($root->childNodes as $child) {
                $result .= $document->saveHTML($child);
            }

            return $result;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    }

    private function legacyAlign(string $style): ?string
    {
        $alignments = [
            'flex-start' => 'left',
            'center' => 'center',
            'flex-end' => 'right',
        ];

        foreach (explode(';', $style) as $declaration) {
            [$property, $value] = array_pad(explode(':', $declaration, 2), 2, null);

            if (strtolower(trim((string) $property)) !== 'justify-content') {
                continue;
            }

            $normalizedValue = strtolower(trim((string) $value));

            return $alignments[$normalizedValue] ?? null;
        }

        return null;
    }

    private function legacyImageAlign(string $classes): ?string
    {
        $alignments = [
            'alignleft' => 'left',
            'aligncenter' => 'center',
            'alignright' => 'right',
        ];

        foreach (preg_split('/\s+/', trim($classes)) ?: [] as $className) {
            $alignment = $alignments[strtolower($className)] ?? null;
            if ($alignment) {
                return $alignment;
            }
        }

        return null;
    }
}
