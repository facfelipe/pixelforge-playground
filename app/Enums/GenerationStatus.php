<?php

declare(strict_types=1);

namespace App\Enums;

enum GenerationStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }

    /**
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Queued => [self::Processing, self::Succeeded, self::Failed],
            self::Processing => [self::Succeeded, self::Failed],
            self::Succeeded, self::Failed => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }
}
