<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Callback;

use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\ScheduledPaymentStatus;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Callback
 *
 * @since version 1.5.0
 */
final class CallbackPayload
{
    private const INITIAL_MARKER = 'INITIAL';

    private function __construct(private readonly array $data)
    {
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function getHppOrderId(): string
    {
        return trim((string) ($this->data['hppOrderId'] ?? $this->getOperation()['hppOrderId'] ?? ''));
    }

    public function getOrderStatus(): ?string
    {
        $status = $this->data['orderStatus'] ?? null;

        return $status !== null && $status !== '' ? (string) $status : null;
    }

    /**
     * @return bool
     */
    public function isRecurring(): bool
    {
        $type = strtoupper(trim((string) ($this->data['recurringTransactionType'] ?? '')));

        if ($type !== '') {
            return !str_contains($type, self::INITIAL_MARKER);
        }

        return $this->getScheduledPaymentId() !== null;
    }

    public function getScheduledPaymentId(): ?string
    {
        $id = $this->data['scheduledPaymentId'] ?? null;

        return $id !== null && $id !== '' ? (string) $id : null;
    }

    /**
     * @return array
     */
    public function getOperation(): array
    {
        if (isset($this->data['operation']) && is_array($this->data['operation'])) {
            return $this->data['operation'];
        }

        if (isset($this->data['operations']) && is_array($this->data['operations']) && $this->data['operations'] !== []) {
            $operations = $this->data['operations'];
            $last = $operations[array_key_last($operations)];

            return is_array($last) ? $last : [];
        }

        return [];
    }

    public function getOperationId(): ?string
    {
        $id = $this->getOperation()['operationId'] ?? null;

        return $id !== null && $id !== '' ? (string) $id : null;
    }

    public function getOperationStatus(): ?string
    {
        $status = $this->getOperation()['status'] ?? null;

        return $status !== null && $status !== '' ? (string) $status : null;
    }

    /**
     * @return int|null
     */
    public function getCoinAmount(): ?int
    {
        $amount = $this->getOperation()['coinAmount'] ?? $this->data['coinAmount'] ?? null;

        return is_numeric($amount) ? (int) $amount : null;
    }

    public function getPaymentNumber(): ?int
    {
        $number = $this->data['paymentNumber'] ?? $this->getOperation()['paymentNumber'] ?? null;

        return is_numeric($number) && (int) $number > 0 ? (int) $number : null;
    }

    /**
     * @return ScheduledPaymentStatus|null
     */
    public function getPaymentStatus(): ?ScheduledPaymentStatus
    {
        $raw = $this->getOperationStatus() ?? $this->getOrderStatus();

        return $raw !== null ? ScheduledPaymentStatus::fromProvider($raw) : null;
    }

    /**
     * @return array
     */
    public function getScheduledPayments(): array
    {
        $items = $this->data['scheduledPayments'] ?? [];
        $result = [];

        if (!is_array($items)) {
            return [];
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = $item['scheduledPaymentId'] ?? $item['id'] ?? null;

            if ($id === null || $id === '') {
                continue;
            }

            $date = $item['scheduledDate'] ?? $item['scheduleDate'] ?? $item['paymentDate'] ?? $item['dateTime'] ?? null;
            $number = $item['paymentNumber'] ?? null;

            $result[] = [
                'id' => (string) $id,
                'date' => is_string($date) && $date !== '' ? $date : null,
                'number' => is_numeric($number) && (int) $number > 0 ? (int) $number : null,
            ];
        }

        return $result;
    }
}
