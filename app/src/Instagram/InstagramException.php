<?php

declare(strict_types=1);

namespace App\Instagram;

use RuntimeException;

final class InstagramException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $classification, // 'retry' | 'defer' | 'fatal'
    ) {
        parent::__construct($message);
    }

    public static function fromGraphError(array $error): self
    {
        $code = (int) ($error['code'] ?? 0);
        $subcode = (int) ($error['error_subcode'] ?? 0);
        $message = (string) ($error['message'] ?? 'Unknown Instagram API error');

        if ($code === 2207003) {
            $message .= ' (this usually means PUBLIC_BASE_URL is not a reachable HTTPS address)';
        }

        return new self(sprintf('%s [code %d%s]', $message, $code, $subcode !== 0 ? '/' . $subcode : ''), self::classify($code, $subcode));
    }

    private static function classify(int $code, int $subcode): string
    {
        $retryCodes = [2207003, 2207052, 2207006, 2207020];
        $deferCodes = [4, 17, 32, 613, 2207042];
        $fatalCodes = [190, 200, 10, 2207050];

        return match (true) {
            in_array($code, $retryCodes, true) => 'retry',
            in_array($code, $deferCodes, true) => 'defer',
            in_array($code, $fatalCodes, true) => 'fatal',
            default => 'retry',
        };
    }
}
