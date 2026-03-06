<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity;

use Alliance\Plugin\Vmpayment\Alliancepay\Services\ConvertData\ConvertDataService;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\DateTime\DateTimeNormalizer;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use VmTable;

defined('_JEXEC') or die();

/**
 * @package     Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity
 *
 * @since version 1.0.0
 */
class AllianceRefundTable extends VmTable
{
    public $refund_id = 0;

    // Fields
    public $order_id = 0;
    public $type = '';
    public $rrn = '';
    public $purpose = null;
    public $comment = null;
    public $coin_amount = 0;
    public $merchant_id = '';
    public $operation_id = '';
    public $ecom_operation_id = '';
    public $merchant_name = null;
    public $approval_code = null;
    public $status = '';
    public $transaction_type = 0;
    public $merchant_request_id = '';
    public $transaction_currency = '';
    public $merchant_commission = null;
    public $creation_date_time = '0000-00-00 00:00:00';
    public $modification_date_time = '0000-00-00 00:00:00';
    public $action_code = null;
    public $response_code = null;
    public $description = null;
    public $processing_merchant_id = '';
    public $processing_terminal_id = '';
    public $transaction_response_info = null; // LONGTEXT
    public $payment_system = null;
    public $product_type = '';
    public $notification_url = '';
    public $payment_service_type = null;
    public $notification_encryption = '';
    public $original_operation_id = '';
    public $original_coin_amount = 0;
    public $original_ecom_operation_id = '';
    public $rrn_original = '';
    public function __construct(
        &$db,
        private readonly DateTimeNormalizer $dateTimeNormalizer,
    ) {
        if (!$db) {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
        }
        parent::__construct('#__virtuemart_payment_plg_alliance_refund', 'refund_id', $db);
    }

    public function bindChecknStoreAllianceRefund($orderRefundData)
    {
        $orderRefundData['transaction_response_info'] =
            json_encode($orderRefundData['transaction_response_info']);
        $orderRefundData['creation_date_time'] =
            $this->dateTimeNormalizer->normalizeDate($orderRefundData['creation_date_time']);
        $orderRefundData['modification_date_time'] =
            $this->dateTimeNormalizer->normalizeDate($orderRefundData['modification_date_time']);

        if (!$this->bind($orderRefundData)) {
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
