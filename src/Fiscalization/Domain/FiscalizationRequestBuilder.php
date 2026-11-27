<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain;

/**
 * Assembles and validates the body of POST /ecom/jws/cashbox/v2/fiscalization/create. Pure PHP.
 *
 * @since version 1.6.0
 */
final class FiscalizationRequestBuilder
{
    /**
     * @param ProductLine[] $lines
     * @param callable(int): ResolvedFiscalAttributes $attributesFor fiscal attributes by product id
     *        (0 = a service line: shipment / payment fee)
     *
     * @return array<string, mixed> the request body
     *
     * @throws FiscalizationValidationException
     */
    public function build(
        string $merchantRequestId,
        string $merchantId,
        string $operationId,
        ?string $receiptComment,
        string $notificationUrl,
        array $lines,
        int $operationKop,
        callable $attributesFor
    ): array {
        $issues = [];

        foreach (['merchantRequestId' => $merchantRequestId, 'merchantId' => $merchantId, 'operationId' => $operationId] as $field => $value) {
            if (trim($value) === '') {
                $issues[] = new FiscalizationIssue(FiscalizationIssue::MISSING_FIELD, [$field]);
            }
        }

        if (trim($notificationUrl) === '') {
            $issues[] = new FiscalizationIssue(FiscalizationIssue::NO_NOTIFICATION_URL);
        }

        if ($lines === []) {
            $issues[] = new FiscalizationIssue(FiscalizationIssue::NO_PRODUCTS);
        }

        $products = [];
        $sumKop = 0;
        $attributeCache = [];

        foreach ($lines as $line) {
            $attributes = $attributeCache[$line->productId] ??= $attributesFor($line->productId);

            if (!$attributes->isValid()) {
                $issues[] = new FiscalizationIssue(FiscalizationIssue::CLASSIFICATION_REQUIRED_FOR_PRODUCT, [$line->name]);
                continue;
            }

            $name = trim($line->name);

            $products[] = [
                'id' => $line->id,
                'name' => $name !== '' ? $name : $line->id,
                'count' => round((float) $line->count, 3),
                'unitPrice' => MoneyConverter::kopecksToUah($line->unitKop),
            ] + $attributes->toProductFields();
            $sumKop += $line->totalKop();
        }

        if ($issues === [] && $sumKop !== $operationKop) {
            $issues[] = new FiscalizationIssue(
                FiscalizationIssue::SUM_MISMATCH,
                [number_format($sumKop / 100, 2, '.', ''), number_format($operationKop / 100, 2, '.', '')]
            );
        }

        if ($issues !== []) {
            throw new FiscalizationValidationException(self::uniqueIssues($issues));
        }

        $request = [
            'merchantRequestId' => $merchantRequestId,
            'merchantId' => $merchantId,
            'operationId' => $operationId,
        ];

        if ($receiptComment !== null && trim($receiptComment) !== '') {
            $request['receiptComment'] = $receiptComment;
        }

        $request['notificationUrl'] = $notificationUrl;
        $request['products'] = $products;

        return $request;
    }

    /**
     * @param FiscalizationIssue[] $issues
     *
     * @return FiscalizationIssue[]
     */
    private static function uniqueIssues(array $issues): array
    {
        $seen = [];
        $result = [];

        foreach ($issues as $issue) {
            $signature = $issue->key . '|' . implode('|', $issue->params);

            if (!isset($seen[$signature])) {
                $seen[$signature] = true;
                $result[] = $issue;
            }
        }

        return $result;
    }
}
