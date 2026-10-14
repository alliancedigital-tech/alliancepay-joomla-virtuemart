<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

namespace Alliance\Component\Alliancepay\Administrator\Helper;

defined('_JEXEC') or die;

use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * @since 1.5.0
 */
final class ProductLookup
{
    private const MIN_TERM_LENGTH = 2;

    /**
     * @return array{id: int, name: string, sku: string, price: float|null, currency: string|null}|null
     */
    public static function find(int $productId): ?array
    {
        if ($productId < 1 || !self::bootstrapVirtueMart()) {
            return null;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = self::baseProductQuery($db)
            ->where($db->quoteName('p.virtuemart_product_id') . ' = :id')
            ->bind(':id', $productId);

        try {
            $db->setQuery($query);
            $row = $db->loadAssoc();
        } catch (\Throwable) {
            return null;
        }

        if (!$row) {
            return null;
        }

        return self::withUahPrice($db, $row);
    }

    /**
     * @param string $term
     * @param int $limit
     * @return array
     */
    public static function search(string $term, int $limit = 20): array
    {
        $term = trim($term);

        if (mb_strlen($term) < self::MIN_TERM_LENGTH || !self::bootstrapVirtueMart()) {
            return [];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
        $query = self::baseProductQuery($db)
            ->where($db->quoteName('l.product_name') . ' LIKE :term')
            ->bind(':term', $like)
            ->where($db->quoteName('p.product_parent_id') . ' = 0')
            ->where(
                $db->quoteName('p.virtuemart_product_id') . ' NOT IN ('
                . 'SELECT DISTINCT ' . $db->quoteName('product_parent_id')
                . ' FROM ' . $db->quoteName('#__virtuemart_products')
                . ' WHERE ' . $db->quoteName('product_parent_id') . ' != 0'
                . ')'
            )
            ->order($db->quoteName('l.product_name') . ' ASC')
            ->setLimit(max(1, $limit));

        try {
            $db->setQuery($query);
            $rows = $db->loadAssocList();
        } catch (\Throwable) {
            return [];
        }

        return array_map(static fn (array $row): array => self::withUahPrice($db, $row), $rows);
    }

    /**
     * @param DatabaseInterface $db
     * @return \Joomla\Database\QueryInterface
     */
    private static function baseProductQuery(DatabaseInterface $db): \Joomla\Database\QueryInterface
    {
        $languageTable = '#__virtuemart_products_' . \VmConfig::$vmlang;

        return $db->getQuery(true)
            ->select([$db->quoteName('p.virtuemart_product_id', 'id'), $db->quoteName('l.product_name', 'name'), $db->quoteName('p.product_sku', 'sku')])
            ->from($db->quoteName($languageTable, 'l'))
            ->join('INNER', $db->quoteName('#__virtuemart_products', 'p') . ' ON ' . $db->quoteName('p.virtuemart_product_id') . ' = ' . $db->quoteName('l.virtuemart_product_id'));
    }

    /**
     * @param DatabaseInterface $db
     * @param array $row
     * @return array
     */
    private static function withUahPrice(DatabaseInterface $db, array $row): array
    {
        $productId = (int) $row['id'];
        $uahCode = Config::SUBSCRIPTION_CURRENCY_CODE;
        // Prefer the base price (no shopper group, no quantity tier) when several UAH rows exist.
        $orderBy = '(' . $db->quoteName('pp.virtuemart_shoppergroup_id') . ' = 0) DESC, '
            . $db->quoteName('pp.price_quantity_start') . ' ASC, '
            . $db->quoteName('pp.virtuemart_product_price_id') . ' ASC';
        $query = $db->getQuery(true)
            ->select($db->quoteName('pp.product_price'))
            ->from($db->quoteName('#__virtuemart_product_prices', 'pp'))
            ->join('INNER', $db->quoteName('#__virtuemart_currencies', 'c') . ' ON ' . $db->quoteName('c.virtuemart_currency_id') . ' = ' . $db->quoteName('pp.product_currency'))
            ->where($db->quoteName('pp.virtuemart_product_id') . ' = :productId')
            ->where($db->quoteName('c.currency_numeric_code') . ' = :uahCode')
            ->bind(':productId', $productId)
            ->bind(':uahCode', $uahCode)
            ->order($orderBy)
            ->setLimit(1);

        $price = null;

        try {
            $db->setQuery($query);
            $raw = $db->loadResult();
            $price = $raw !== null ? round((float) $raw, 2) : null;
        } catch (\Throwable) {
            $price = null;
        }

        return [
            'id' => $productId,
            'name' => (string) $row['name'],
            'sku' => (string) $row['sku'],
            'price' => $price,
            'currency' => $price !== null ? 'UAH' : null,
        ];
    }

    private static function bootstrapVirtueMart(): bool
    {
        if (!class_exists('VmConfig')) {
            $config = JPATH_ADMINISTRATOR . '/components/com_virtuemart/helpers/config.php';

            if (!is_file($config)) {
                return false;
            }

            require_once $config;
        }

        try {
            \VmConfig::loadConfig();
        } catch (\Throwable) {
            return false;
        }

        return true;
    }
}
