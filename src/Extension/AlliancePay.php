<?php
namespace Alliance\Plugin\Vmpayment\Alliancepay\Extension;

defined('_JEXEC') or die;

if (!class_exists('vmPSPlugin')) {
    require_once(JPATH_ADMINISTRATOR . '/components/com_virtuemart/plugins/vmpsplugin.php');
}

use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity\AllianceOrderTable;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity\AllianceRefundTable;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Payment\PaymentProcessor;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Payment\RefundProcessor;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Callback\CallbackProcessor;
use CurrencyDisplay;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use JUri;
use shopFunctionsF;
use stdClass;
use VirtueMartCart;
use VirtueMartModelOrders;
use VmConfig;
use VmModel;
use vmPSPlugin;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Authorization\AuthorizationService;
use vRequest;

/**
 * @package     Alliance\Plugin\Vmpayment\Alliancepay\Extension
 *
 * @since version 1.0.0
 */
class AlliancePay extends vmPSPlugin
{
    private const FULL_REFUND_TRIGGER = 'processFullRefund';

    private const PARTIAL_REFUND_TRIGGER = 'processPartialRefund';

    private const CHECK_ORDER_STATUS_TRIGGER = 'checkOrderStatus';

    private const ACTION_CALLBACK = 'callback';

    private const ACTION_AUTHORIZATION = 'authorization';

    public function __construct(
        private readonly PaymentProcessor $paymentProcessor,
        private readonly RefundProcessor $refundProcessor,
        private readonly CallbackProcessor $callbackProcessor,
        private readonly Config $allianceConfig,
        private readonly AllianceOrderTable $allianceOrderTable,
        private readonly AllianceRefundTable $allianceRefundTable,
        private readonly AuthorizationService $authorizationService,
        $subject,
        $config = []
    ) {
        parent::__construct($subject, $config);
        $this->tableColumns = array('virtuemart_order_id', 'payment_method_id', 'transaction_id', 'status');
        $varsToPush = $this->getVarsToPush();
        $this->addVarsToPushCore($varsToPush, 1);
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);

        Log::addLogger(
            ['text_file' => 'plg_vmpayment_alliance.log.php'],
            Log::ALL,
            ['alliance_payment']
        );
    }

    /**
     *
     * @return true
     *
     * @throws Exception
     * @since version 1.0.0
     */
    public function plgVmOnPaymentNotification()
    {
        $app = Factory::getApplication();
        $input = $app->input;
        $orderId = $input->getInt('onme');
        $action = $input->get('action');
        $isPartialRefund = $input->getBool('partialRefund', false);
        $data = json_decode(file_get_contents('php://input'), true);

        if (!is_null($data) && $action == self::ACTION_CALLBACK && $orderId) {
            $this->callbackProcessor->processCallback($orderId, $data);
            if (!empty($data['operation'])
                && !empty($data['operation']['type'])
                && !empty($data['operation']['status'])
            ) {
                $newStatus = null;

                if ($data['operation']['type'] === 'PURCHASE') {
                    $newStatus = $data['operation']['status'] === 'SUCCESS'
                        ? $this->allianceConfig->getSuccessPaymentStatus() : $this->allianceConfig->getFailPaymentStatus();
                }

                if ($data['operation']['type'] === 'REFUND') {
                    $db = Factory::getContainer()->get(DatabaseInterface::class);
                    $query = $db->getQuery(true);
                    $query->select('SUM(' . $db->quoteName('coin_amount') . ')')
                        ->from($this->allianceRefundTable->get('_tbl'))
                        ->where('order_id=' . $orderId)
                        ->where('status=\'SUCCESS\'');
                    $db->setQuery($query);
                    $totalRefunds = $db->loadResult();

                    if ($data['coinAmount'] === (int)$totalRefunds) {
                        if ($isPartialRefund) {
                            $newStatus = $this->allianceConfig->getSuccessRefundStatus();
                        } else {
                            $newStatus = $data['operation']['status'] === 'SUCCESS'
                                ? $this->allianceConfig->getSuccessRefundStatus() : $this->allianceConfig->getFailRefundStatus();
                        }

                    }
                }

                if ($orderId && $newStatus) {
                    $modelOrder = VmModel::getModel('orders');
                    $modelOrder->updateStatusForOneOrder($orderId, ['order_status' => $newStatus], true);
                }
            }
        }

        return null;
    }

    /**
     * @param VirtueMartCart $cart
     * @param $selected
     * @param $htmlIn
     *
     * @return bool
     *
     * @since version 1.0.0
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected, &$htmlIn)
    {
        $method = $this->getVmPluginMethod($selected);
        $viewData = [
            'logo' => JURI::root() . 'plugins/vmpayment/alliancepay/assets/logo.png',
            'payment_name' => $method->payment_name,
            'payment_description' => $method->payment_desc,
        ];

        $methodHtml = $this->renderByLayout('alliance_pay', $viewData);
        $htmlIn[] = $methodHtml;

        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    /**
     * @param $data
     *
     * @return bool
     *
     * @since version 1.0.0
     */
    public function plgVmDeclarePluginParamsPaymentVM3(&$data)
    {
        return $this->declarePluginParams('payment', $data);
    }

    /**
     * @param $name
     * @param $id
     * @param $table
     *
     * @return bool
     *
     * @since version 1.0.0
     */
    public function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    /**
     *
     * @return Container|false
     *
     * @since version 1.0.0
     */
    private function getContainer() : ?Container
    {
        try {
            return Factory::getContainer();
        } catch (Exception $exception) {
            Log::add(
                $exception->getMessage(),
                Log::ERROR,
                'alliance_payment'
            );
        }

        return false;
    }

    /**
     * @param $cart
     * @param $order
     *
     * @return true
     *
     * @throws Exception
     * @since version 1.0.0
     */
    public function plgVmConfirmedOrder($cart, $order)
    {
        if (!($method = $this->getVmPluginMethod ($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null;
        }

        if (!$this->selectedThisElement ($method->payment_element)) {
            return false;
        }

        $orderResult = $this->paymentProcessor->processPayment($order);

        if (!empty($orderResult['redirectUrl'])) {
            $app = Factory::getApplication();
            $app->redirect($orderResult['redirectUrl']);
        }

        return true;
    }

    /**
     * @param $type
     * @param $name
     * @param $render
     *
     * @return bool
     *
     * @throws Exception
     * @since version 1.0.0
     */
    public function plgVmOnSelfCallBE($type, $name, &$render): bool
    {
        $app = Factory::getApplication();
        $input = $app->input;
        $task = $input->get('task');
        $action = $input->get('action');
        $orderId = $input->getInt('onme');
        $amount = $input->getFloat('amount');
        $request = vRequest::getRequest();
        $itemIds = isset($request['item_ids']) ? explode(',', $request['item_ids']) : [];

        if ($task === self::PARTIAL_REFUND_TRIGGER && !empty($itemIds) && !empty($amount) && !empty($orderId)) {
            $isAuthorized = Factory::getApplication()
                ->getIdentity()
                ->authorise('core.manage', 'com_virtuemart');
            if (!$isAuthorized) {
                return false;
            }

            $refundAmount = (int)($amount * 100);
            $refundResult = $this->refundProcessor->processRefund($orderId, $refundAmount);
            $orderModel = VmModel::getModel('orders');
            $order = $orderModel->getOrder($orderId);

            if ($refundResult['success']) {
                foreach ($order['items'] as $item) {
                    if (in_array($item->virtuemart_order_item_id, $itemIds)) {
                        $item->order_status = $this->allianceConfig->getSuccessRefundStatus();
                        $orderModel->updateSingleItem($item->virtuemart_order_item_id, $item);
                    }
                }
                $response = json_encode(
                    [
                        'success' => true,
                        'message' => 'AlliancePay: Partial refund processed successfully.'
                    ]
                );
            } else {
                foreach ($order['items'] as $item) {
                    if (in_array($item->virtuemart_order_item_id, $itemIds)) {
                        $item->order_status = $this->allianceConfig->getFailRefundStatus();
                        $orderModel->updateSingleItem($item->virtuemart_order_item_id, $item);
                    }
                }
                $response = json_encode(
                    [
                        'success' => false,
                        'message' => 'AlliancePay: Processing partial refund failed.'
                    ]
                );
            }

            $this->sendAjaxResponse($response);
        }

        if ($task === self::FULL_REFUND_TRIGGER && !empty($orderId)) {
            $result = $this->refundProcessor->processRefund($orderId);
            $orderModel = VmModel::getModel('orders');

            if ($result['success']) {
                $orderModel->updateStatusForOneOrder(
                    $orderId,
                    ['order_status' => $this->allianceConfig->getSuccessRefundStatus()],
                    true
                );
                $response = json_encode(
                    [
                        'success' => true,
                        'message' => 'Alliance: Full refund processed successfully.'
                    ]
                );
            } else {
                $orderModel->updateStatusForOneOrder(
                    $orderId,
                    ['order_status' => $this->allianceConfig->getSuccessRefundStatus()],
                    true
                );
                $response = json_encode(
                    [
                        'success' => false,
                        'message' => 'Alliance Refund Error: ' . $result['message']
                    ]
                );
            }

            $this->sendAjaxResponse($response);
        }

        if ($task === self::CHECK_ORDER_STATUS_TRIGGER && !empty($orderId)) {
            $result = $this->paymentProcessor->updateOrderData($orderId);

            if ($result) {
                $response = json_encode(['success' => true , 'message' => 'AlliancePay update successful.']);
            } else {
                $response = json_encode(
                    [
                        'success' => false,
                        'message' => 'AlliancePay update status failed. Check error log.'
                    ]
                );
            }

            $this->sendAjaxResponse($response);
        }

        if ($action === self::ACTION_AUTHORIZATION) {
            $serviceCode = $this->allianceConfig->getServiceCode();

            if (empty($serviceCode)) {
                return false;
            }

            $result = $this->authorizationService->authorize($serviceCode);

            if (!empty($result)) {
                $response = json_encode(['success' => true , 'message' => 'Authorization successfully!']);
            } else {
                $response = json_encode(['success' => false, 'message' => 'Authorization failed!']);
            }

            $this->sendAjaxResponse($response);
        }

        return true;
    }

    /**
     * @param string $response
     *
     *
     * @throws Exception
     * @since version 1.0.0
     */
    private function sendAjaxResponse(string $response)
    {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response);
        Factory::getApplication()->close();
    }

    /**
     * @param int $orderId
     * @param string $payment
     *
     * @return string
     *
     * @since version 1.0.0
     */
    public function plgVmOnShowOrderBEPayment(int $orderId, string $payment): string
    {
        $allianceOrder = $this->allianceOrderTable->load($orderId, 'order_id');
        $callbackData = json_decode($allianceOrder->callback_data, true);
        $operations = $callbackData['operations'] ?? [];
        $transactions = [];

        foreach ($operations as $operation) {
            $transactions[] = [
                'type' => $operation['type'],
                'coinAmount' => $operation['coinAmount'],
                'operationId' => $operation['operationId'] ?? '',
                'status' => $operation['status'],
                'creationDateTime' => $operation['creationDateTime'],
            ];
        }

        $html = $this->renderByLayout('callback_info', ['transactions' => $transactions]);

        $orderModel = VmModel::getModel('orders');
        $order = $orderModel->getOrder($orderId);
        $isFullRefundAllowed = true;

        foreach ($order['items'] as $item) {
            if ($item->order_status === $this->allianceConfig->getSuccessRefundStatus()) {
                $isFullRefundAllowed = false;
                break;
            }
        }

        $viewData = new stdClass();
        $viewData->order_id = $orderId;
        $viewData->items = $order['items'];
        $viewData->fullRefundTriger = self::FULL_REFUND_TRIGGER;
        $viewData->isFullRefundAllowed = $isFullRefundAllowed;
        $viewData->partialRefundTrigger = self::PARTIAL_REFUND_TRIGGER;

        $html .= $this->renderByLayout('refund_form', (array)$viewData);

        return $html;
    }

    /**
     * @param $html
     *
     * @return bool|null
     *
     * @throws Exception
     * @since version 1.0.0
     */
    public function plgVmOnPaymentResponseReceived(&$html)
    {
        $app = Factory::getApplication();
        $input = $app->input;
        $orderId = $input->get('oid');
        $orderModel = VmModel::getModel('orders');
        $order = $orderModel->getOrder($orderId);

        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null;
        }

        if (!$this->selectedThisElement ($method->payment_element)) {
            return false;
        }

        $methodProperties = $method->getProperties();
        $currency = CurrencyDisplay::getInstance(
            $order['details']['BT']->user_currency_id,
            $order['details']['BT']->virtuemart_vendor_id
        );
        $orderTotal = $order['details']['BT']->order_total;
        $formattedTotal = $currency->priceDisplay($orderTotal);

        $orderlink='';
        $tracking = VmConfig::get('ordertracking','guests');
        if ($tracking !='none' && !($tracking =='registered' && empty($order['details']['BT']->virtuemart_user_id) )) {

            $orderlink = 'index.php?option=com_virtuemart&view=orders&layout=details&order_number='
                . $order['details']['BT']->order_number;

            if ($tracking == 'guestlink'
                || ($tracking == 'guests' && empty($order['details']['BT']->virtuemart_user_id))
            ) {
                $orderlink .= '&order_pass=' . $order['details']['BT']->order_pass;
            }
        }

        $html = $this->renderByLayout('post_payment',
            [
                'order_number' => $order['details']['BT']->order_number,
                'order_pass' => $order['details']['BT']->order_pass,
                'payment_name' => $methodProperties['payment_name'],
                'display_total_in_payment_currency' => $formattedTotal,
                'order_link' => $orderlink,
                'method' => $method
            ]
        );

        $cart = VirtueMartCart::getCart();
        $cart->emptyCart ();
        vRequest::setVar ('html', $html);

        return true;
    }

    /**
     *
     * @return bool|null
     *
     * @throws Exception
     * @since version 1.0.0
     */
    public function plgVmOnUserPaymentCancel()
    {
        $app = Factory::getApplication();
        $input = $app->input;
        $orderId = $input->get('order_id');
        $orderModel = VmModel::getModel('orders');
        $order = $orderModel->getOrder($orderId);

        if (!($method = $this->getVmPluginMethod ($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null;
        }

        if (!$this->selectedThisElement ($method->payment_element)) {
            return false;
        }

        if ($orderId) {
            if ($orderId) {
                $modelOrder = VmModel::getModel('orders');
                $modelOrder->updateStatusForOneOrder(
                    $orderId,
                    [
                        'order_status' => $this->allianceConfig->getFailPaymentStatus()
                    ],
                    true
                );
            }
        }

        return true;
    }

    /**
     * @param string $statusCode
     *
     * @return string
     *
     * @since version 1.0.0
     */
    public function getStatusName(string $statusCode): string
    {
        return shopFunctionsF::getOrderStatusName($statusCode);
    }
}
