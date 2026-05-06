<?php

namespace App\Traits;

trait SanitizesOrderBy
{
    private function sanitizeDirection(?string $direction): string
    {
        return 'desc' === strtolower((string) $direction) ? 'DESC' : 'ASC';
    }
}
