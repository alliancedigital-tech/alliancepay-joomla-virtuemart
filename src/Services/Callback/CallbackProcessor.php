<?php
/**
 * @package     Alliance\Plugin\Vmpayment\Alliancepay\Services\Callback
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Alliance\Plugin\Vmpayment\Alliancepay\Services\Callback;

use Alliance\Plugin\Vmpayment\Alliancepay\Services\UpdateOrder\UpdateAllianceOrder;
use Exception;
use Joomla\CMS\Log\Log;

/**
 * @package     Alliance\Plugin\Vmpayment\Alliancepay\Services\Callback
 *
 * @since version 1.0.0
 */
class CallbackProcessor
{
    public function __construct(
        private readonly UpdateAllianceOrder $updateAllianceOrder,
    ) {
        Log::addLogger(
            ['text_file' => 'plg_vmpayment_alliance.log.php'],
            Log::ALL,
            ['alliance_payment']
        );
    }

    /**
     * @param int $orderId
     * @param array $data
     *
     *
     * @since version 1.0.0
     */
    public function processCallback(int $orderId, array $data): void
    {
        try {
            $updateOrderResult = $this->updateAllianceOrder->updateAllianceOrder($orderId, $data);
            $updateRefundResult = $this->updateRefundIfExists($data);

            if (!$updateOrderResult || !$updateRefundResult) {
                Log::add('Failed to update alliance order: ' . $orderId, Log::ERROR);
            }
        } catch (Exception $exception) {
            Log::add($exception->getMessage(), Log::ERROR);
        }
    }

    /**
     * @param array $data
     *
     * @return bool
     *
     * @since version 1.0.2
     */
    private function updateRefundIfExists(array $data): bool
    {
        if (!empty($data['operation']['type'])
            && $data['operation']['type'] === 'REFUND'
        ) {
            return $this->updateAllianceOrder->updateAllianceRefund(
                $data['operation']
            );
        }

        return true;
    }
}
