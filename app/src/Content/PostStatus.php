<?php

declare(strict_types=1);

namespace App\Content;

enum PostStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Generating = 'generating';
    case Ready = 'ready';
    case Publishing = 'publishing';
    case Published = 'published';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';

    public function editable(): bool
    {
        return $this === self::Draft || $this === self::Pending || $this === self::Ready;
    }
}
