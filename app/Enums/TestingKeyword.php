<?php

namespace App\Enums;

enum TestingKeyword: string
{
    case Blocked = '[blocked]';
    case Overloaded = '[overloaded]';
    case Fail = '[fail]';
    case Succeed = '[succeed]';
    case Slow = '[slow]';
    case Duplicate = '[duplicate]';

    public function description(): string
    {
        return match ($this) {
            self::Blocked => 'Triggers 400 content_policy_violation at submission (unbilled).',
            self::Overloaded => 'Triggers 503 overloaded at submission.',
            self::Fail => 'Accepted, then fails during processing with generation.failed (unbilled).',
            self::Succeed => 'Accepted and guaranteed to succeed without random failure.',
            self::Slow => 'Simulates slow generation (60 to 90 seconds).',
            self::Duplicate => 'Simulates duplicate webhook deliveries for all events.',
        };
    }

    /**
     * @return array<array{keyword: string, label: string, description: string}>
     */
    public static function all(): array
    {
        return array_map(fn (self $case) => [
            'keyword' => $case->value,
            'label' => ucfirst(trim($case->value, '[]')),
            'description' => $case->description(),
        ], self::cases());
    }
}
