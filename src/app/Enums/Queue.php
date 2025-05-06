<?php

namespace App\Enums;

/**
 * Enumeration of existing queues
 */
enum Queue: string
{
    // Note: The order defines queues priority
    case Default = 'default';
    case Mail = 'mail';
    case Background = 'background';

    /**
     * Get all cases' values
     */
    public static function values(): array
    {
        return array_map(static fn ($case) => $case->value, self::cases());
    }
}
