<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Http;

use RuntimeException;
use Throwable;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Http
 *
 * @since version 1.5.0
 */
class ProviderApiException extends RuntimeException
{
    /**
     * @param string $message
     * @param int $httpStatus
     * @param string|null $msgType
     * @param array $validationErrors
     * @param string $rawBody
     * @param Throwable|null $previous
     */
    public function __construct(
        string $message,
        private readonly int $httpStatus = 0,
        private readonly ?string $msgType = null,
        private readonly array $validationErrors = [],
        private readonly string $rawBody = '',
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getMsgType(): ?string
    {
        return $this->msgType;
    }

    /**
     * @return string[]
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    /**
     * @return string
     */
    public function getAdminMessage(): string
    {
        if ($this->validationErrors !== []) {
            return $this->getMessage() . ': ' . implode('; ', $this->validationErrors);
        }

        return $this->getMessage();
    }
}
