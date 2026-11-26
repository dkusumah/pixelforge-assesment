<?php

namespace App\Enums;

enum GenerationStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isTerminal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }
}
