<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * The fiscalization data of a synchronous response or a callback, tolerant to missing fields.
 * Dates are converted to UTC 'Y-m-d H:i:s'.
 *
 * @since version 1.6.0
 */
final class ParsedFiscalization
{
    public function __construct(
        public readonly ?FiscalizationStatus $status,
        public readonly ?string $operationId,
        public readonly ?string $merchantRequestId,
        public readonly ?string $operationType,
        public readonly ?string $bucketId,
        public readonly ?string $fiscalNumber,
        public readonly ?string $receiptUrl,
        public readonly ?string $fiscalizationDateTime,
        public readonly ?string $fiscalizationModificationDateTime,
        public readonly ?string $modificationDateTime,
        public readonly ?string $errorCode,
        public readonly ?string $errorDescription
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            FiscalizationStatus::fromApi(self::string($payload['status'] ?? null)),
            self::string($payload['operationId'] ?? null),
            self::string($payload['merchantRequestId'] ?? null),
            self::string($payload['operationType'] ?? null),
            self::string($payload['bucketId'] ?? null),
            self::string($payload['fiscalNumber'] ?? null),
            self::string($payload['receiptUrl'] ?? null),
            self::utc($payload['fiscalizationDateTime'] ?? null),
            self::utc($payload['fiscalizationModificationDateTime'] ?? null),
            self::utc($payload['modificationDateTime'] ?? null),
            self::string($payload['errorCode'] ?? null),
            self::string($payload['errorDescription'] ?? null)
        );
    }

    /**
     * The modification time used for ordering updates: the record's own, else the fiscal document's.
     */
    public function modifiedAt(): ?string
    {
        return $this->modificationDateTime ?? $this->fiscalizationModificationDateTime;
    }

    public static function utc(mixed $value): ?string
    {
        $value = self::string($value);

        if ($value === null) {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    private static function string(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
