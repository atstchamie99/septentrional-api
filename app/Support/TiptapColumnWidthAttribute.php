<?php

namespace App\Support;

use HTMLPurifier_AttrDef;

final class TiptapColumnWidthAttribute extends HTMLPurifier_AttrDef
{
    public const MAX_WIDTH = 4096;

    public const MAX_ENTRIES = 32;

    public function validate($string, $config, $context)
    {
        $entries = array_map('trim', explode(',', $this->parseCDATA($string)));

        if (
            $entries === []
            || count($entries) > self::MAX_ENTRIES
        ) {
            return false;
        }

        $widths = [];
        foreach ($entries as $entry) {
            if (! preg_match('/^[1-9]\d*$/', $entry)) {
                return false;
            }

            $width = (int) $entry;
            if ($width > self::MAX_WIDTH) {
                return false;
            }

            $widths[] = $width;
        }

        return implode(',', $widths);
    }
}
