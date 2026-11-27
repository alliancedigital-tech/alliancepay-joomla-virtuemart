<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Service;

use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalAttributeResolver;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\FiscalDefaults;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Domain\ResolvedFiscalAttributes;
use Alliance\Plugin\Vmpayment\Alliancepay\Fiscalization\Repository\ProductFiscalAttributeRepository;

/**
 * Fiscal values of an order item's product: product field (with VirtueMart inheritance) -> default from
 * the settings. Used by both automatic and manual fiscalization.
 *
 * @since version 1.6.0
 */
class ProductFiscalAttributeService
{
    public function __construct(
        private readonly ProductFiscalAttributeRepository $repository,
        private readonly FiscalAttributeResolver $resolver,
        private readonly Config $config
    ) {
    }

    public function resolveForProduct(int $productId): ResolvedFiscalAttributes
    {
        // productId 0 is a service line (shipment / payment fee): the defaults only.
        $raw = $productId > 0 ? $this->repository->findRawValues($productId) : [];

        return $this->resolver->resolve($raw, $this->defaults());
    }

    public function defaults(): FiscalDefaults
    {
        return new FiscalDefaults(
            $this->config->getFiscalizationDefaultTaxCode(),
            $this->config->getFiscalizationDefaultClassificationCode(),
            $this->config->getFiscalizationDefaultExciseLabels(),
            $this->config->getFiscalizationDefaultMeasurementCode()
        );
    }
}
