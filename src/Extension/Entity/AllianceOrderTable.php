<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity;

use Alliance\Plugin\Vmpayment\Alliancepay\Services\DateTime\DateTimeNormalizer;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use VmTable;

defined('_JEXEC') or die();

/**
 * @package     Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity
 *
 * @since version 1.0.0
 */
class AllianceOrderTable extends VmTable
{
    public $entity_id = 0;
    public $order_id = 0;
    public $merchant_request_id = '';
    public $hpp_order_id = '';
    public $merchant_id = '';
    public $coin_amount = 0;
    public $hpp_pay_type = '';
    public $order_status = '';
    public $payment_methods = '';
    public $create_date = '0000-00-00 00:00:00';
    public $updated_at = '0000-00-00 00:00:00';
    public $operation_id = '';
    public $ecom_order_id = '';
    public $is_callback_returned = 0;
    public $callback_data = '';
    public $expired_order_date = '0000-00-00 00:00:00';

    public function __construct(
        &$db,
        private readonly DateTimeNormalizer $dateTimeNormalizer
    ) {
        if (!$db) {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
        }
        parent::__construct('#__virtuemart_payment_plg_alliance_order', 'entity_id', $db);

        Log::addLogger(
            ['text_file' => 'plg_vmpayment_alliance.log.php'],
            Log::ALL,
            ['alliance_payment']
        );
    }

    /**
     * @param $orderData
     *
     * @return true
     *
     * @throws Exception
     * @since version 1.0.0
     */
    public function bindChecknStoreAllianceOrder($orderData)
    {
        try {
            $orderData['payment_methods'] = json_encode($orderData['payment_methods']);
            $orderData['create_date'] =
                $this->dateTimeNormalizer->normalizeDate($orderData['create_date']);
            $orderData['expired_order_date'] =
                $this->dateTimeNormalizer->normalizeDate($orderData['expired_order_date']);
        } catch (Exception $exception) {
            Log::add($exception->getMessage(), Log::ERROR);
        }

        if (!empty($orderData['operations'])) {
            unset($orderData['operations']);
        }

        if (!$this->bind($orderData)) {
            throw new Exception('Помилка прив’язки даних: ' . $this->getError());
        }

        if (!$this->check()) {
            throw new Exception('Помилка валідації: ' . $this->getError());
        }

        if (!$this->store()) {
            throw new Exception('Помилка збереження в БД: ' . $this->getError());
        }

        return true;
    }
}
