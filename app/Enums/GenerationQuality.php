<?php

namespace App\Enums;

enum GenerationQuality: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
