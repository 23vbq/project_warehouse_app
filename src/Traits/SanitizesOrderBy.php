<?php

namespace App\Traits;

trait SanitizesOrderBy
{
    private function sanitizeDirection(?string $direction): string
    {
        return strtolower((string) $direction) === 'desc' ? 'DESC' : 'ASC';
    }
}
