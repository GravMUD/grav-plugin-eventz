<?php

declare(strict_types=1);

namespace Grav\Plugin\Eventz;

use Grav\Common\Grav;

final class MudEventzData
{
    public static function dir(Grav $grav, string $subdir): string
    {
        $base = $grav['locator']->findResource('user-data://eventz', true, true);
        $dir = rtrim($base, '/\\') . '/' . trim($subdir, '/');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir;
    }
}
