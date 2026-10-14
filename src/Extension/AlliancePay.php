<?php
namespace Alliance\Plugin\Vmpayment\Alliancepay\Extension;

defined('_JEXEC') or die;

if (!class_exists('vmPSPlugin')) {
    require_once(JPATH_ADMINISTRATOR . '/components/com_virtuemart/plugins/vmpsplugin.php');
}

use Alliance\Plugin\Vmpayment\Alliancepay\Config\Config;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity\AllianceOrderTable;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity\AllianceRefundTable;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Payment\CompletionProcessor;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Payment\PaymentProcessor;
use Alliance\Plugin\Vmpayment\Alliancepay\Extension\Payment\RefundProcessor;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Callback\CallbackProcessor;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Callback\CallbackPayload;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Callback\CallbackResult;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Callback\SubscriptionCallbackHandler;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Checkout\CartInspection;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Checkout\CheckoutGuard;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\Subscription;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\ScheduledPaymentRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\SubscriptionRepository;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\SubscriptionCancelService;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\SubscriptionOrderProcessor;
use CurrencyDisplay;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use JUri;
use shopFunctionsF;
use stdClass;
use Throwable;
use VirtueMartCart;
use VmConfig;
use VmModel;
use vmPSPlugin;
use Alliance\Plugin\Vmpayment\Alliancepay\Services\Jws\JwsVerifier;
use Joomla\CMS\Language\Text;
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

    private const SELF_CALL_CANCEL_SUBSCRIPTION = 'cancelSubscription';

    public function __construct(
        private readonly PaymentProcessor $paymentProcessor,
        private readonly RefundProcessor $refundProcessor,
        private readonly CallbackProcessor $callbackProcessor,
        private readonly Config $allianceConfig,
        private readonly AllianceOrderTable $allianceOrderTable,
        private readonly AllianceRefundTable $allianceRefundTable,
        private readonly JwsVerifier $jwsVerifier,
        private readonly CompletionProcessor $completionProcessor,
        private readonly SubscriptionCallbackHandler $subscriptionCallbackHandler,
        private readonly SubscriptionOrderProcessor $subscriptionOrderProcessor,
        private readonly CheckoutGuard $checkoutGuard,
        private readonly SubscriptionCancelService $subscriptionCancelService,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly ScheduledPaymentRepository $scheduledPaymentRepository,
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
        [$data, $isSignatureVerified] = $this->readCallbackPayload();

        if (!is_null($data) && $this->processSubscriptionCallback($data, $isSignatureVerified)) {
            $this->finishCallbackResponse();
        }

        if (!is_null($data) && $action == self::ACTION_CALLBACK && $orderId) {
            $this->callbackProcessor->processCallback($orderId, $data);
            if (!empty($data['operation'])
                && !empty($data['operation']['type'])
                && !empty($data['operation']['status'])
            ) {
                $newStatus = null;
                $operrationTypes = [
                    Config::OPERATION_TYPE_PURCHASE,
                    Config::OPERATION_TYPE_A2A,
                ];

                $preAuthOperationTypes = [
                    Config::OPERATION_TYPE_PREAUTH,
                    Config::OPERATION_TYPE_COMPLETION
                ];

                if (in_array($data['operation']['type'], $operrationTypes)) {
                    $newStatus = $data['operation']['status'] === 'SUCCESS'
                        ? $this->allianceConfig->getSuccessPaymentStatus() : $this->allianceConfig->getFailPaymentStatus();
                }

                if (in_array( $data['operation']['type'], $preAuthOperationTypes)) {
                    $newStatus = null;
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

        if ($action == self::ACTION_CALLBACK) {
            $this->finishCallbackResponse();
        }

        return null;
    }

    /**
     * Ends the request with an empty body so VirtueMart does not render the site page into the bank's callback
     * response. Keeps any status code already set (403/500 from the subscription handler), otherwise 200.
     *
     * @return never
     *
     * @throws Exception
     * @since version 1.5.0
     */
    private function finishCallbackResponse(): never
    {
        $statusCode = http_response_code() ?: 200;

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($statusCode);
        Factory::getApplication()->close();
        exit;
    }

    /**
     * @param $orderDetails
     * @param $oldOrderDetails
     *
     * @return bool
     *
     * @since version 1.3.0
     */
    public function plgVmOnUpdateOrderPayment($orderDetails, $oldOrderDetails): bool
    {
        $completionTriggerStatus = $this->allianceConfig->getCompletionTriggerStatus();

        if (empty($completionTriggerStatus)) {
            return true;
        }

        if ($orderDetails->order_status !== $completionTriggerStatus) {
            return true;
        }

        $orderId = (int) $orderDetails->virtuemart_order_id;

        if (!$orderId) {
            return true;
        }

        try {
            $allianceOrder = $this->allianceOrderTable->load($orderId, 'order_id');
            $allianceOrderData = $allianceOrder->getProperties();
        } catch (Exception $e) {
            Log::add(
                'plgVmOnUpdateOrderPayment: failed to load alliance order for order_id=' . $orderId . ': ' . $e->getMessage(),
                Log::ERROR,
                'alliance_payment'
            );

            return true;
        }

        if (($allianceOrderData['hpp_pay_type'] ?? '') !== Config::HPP_PAY_TYPE_PREAUTH) {
            return true;
        }

        $orderModel = VmModel::getModel('orders');
        $order = $orderModel->getOrder($orderId);
        $bt = $order['details']['BT'];
        $userCurrRate = (float)($bt->user_currency_rate ?: 1.0);
        $paymentAmount = round((float)($bt->order_total ?? 0) * $userCurrRate, 2);
        $newCoinAmount = (int)round($paymentAmount * 100);

        $result = $this->completionProcessor->processCompletion($orderId, $newCoinAmount);

        if (!$result['success']) {
            $entry = 'plgVmOnUpdateOrderPayment: failed to process completion for order_id='
                . $orderId
                . ': '
                . $result['message'];
            Log::add(
                $entry,
                Log::ERROR,
                'alliance_payment'
            );

            $previousStatus = $oldOrderDetails->order_status ?? '';
            if (!empty($previousStatus)) {
                $orderModel->updateStatusForOneOrder(
                    $orderId,
                    ['order_status' => $previousStatus],
                    true
                );
            }

            return false;
        }

        return true;
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
        $result = $this->setOnTablePluginParams($name, $id, $table);

        if ($result) {
            $paymentType = vRequest::getCmd('payment_type', '');

            if ($paymentType === Config::HPP_PAY_TYPE_A2A
                && $this->allianceConfig->hasNonUahAcceptedCurrency()
            ) {
                Factory::getApplication()->enqueueMessage(
                    Text::_('VMPAYMENT_ALLIANCEPAY_A2A_NON_UAH_WARNING'),
                    'warning'
                );
            }
        }

        return $result;
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

        if ($this->subscriptionOrderProcessor->isSubscriptionOrder($order)) {
            return $this->confirmSubscriptionOrder($order);
        }

        $orderResult = $this->paymentProcessor->processPayment($order);

        if (($orderResult['error'] ?? '') === 'unsupported_currency') {
            Factory::getApplication()->enqueueMessage(
                Text::_('VMPAYMENT_ALLIANCEPAY_UNSUPPORTED_CURRENCY_ERROR'),
                'error'
            );

            return false;
        }

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

        return true;
    }

    /**
     * Reads the callback body.
     *
     * @return array{0: ?array, 1: bool} the decoded payload and whether its signature was verified.
     *     A payload that is not a signed token is still returned (legacy behaviour for one-off payments)
     *     but flagged as unverified; subscription handling never accepts such a payload.
     *
     * @since version 1.5.0
     */
    private function readCallbackPayload(): array
    {
        $raw = file_get_contents('php://input');

        Log::add(
            'Webhook JWS raw data handler: ' . print_r($raw, true),
            Log::INFO,
            'alliance_payment'
        );

        $trimmed = trim((string) $raw);
        $isToken = $trimmed !== '' && !str_contains($trimmed, ' ');

        if ($isToken && substr_count($trimmed, '.') === 2) {
            try {
                return [$this->jwsVerifier->verify($trimmed), true];
            } catch (Throwable $e) {
                Log::add(
                    'Webhook JWS verification failed: ' . $e->getMessage(),
                    Log::ERROR,
                    'alliance_payment'
                );

                return [null, false];
            }
        }

        $data = json_decode($raw, true);

        Log::add(
            'Webhook JWS data handler: ' . print_r($data, true),
            Log::INFO,
            'alliance_payment'
        );

        return [is_array($data) ? $data : null, false];
    }

    /**
     * Handles a callback that belongs to a subscription.
     *
     * @param array $data
     * @param bool $isSignatureVerified
     *
     * @return bool true when the delivery belongs to a subscription and must not go through the legacy flow
     *
     * @since version 1.5.0
     */
    private function processSubscriptionCallback(array $data, bool $isSignatureVerified): bool
    {
        $payload = CallbackPayload::fromArray($data);
        $hppOrderId = $payload->getHppOrderId();

        if ($hppOrderId === '') {
            return false;
        }

        try {
            if (!$isSignatureVerified) {
                // The lookup only decides how strict to be; nothing from an unsigned body is ever applied.
                if ($this->subscriptionRepository->findByProviderOrderId($hppOrderId) === null) {
                    return false;
                }

                Log::add(
                    'Unsigned callback for a subscription order rejected (hppOrderId=' . $hppOrderId . ')',
                    Log::ERROR,
                    'alliance_payment'
                );
                http_response_code(403);

                return true;
            }

            $result = $this->subscriptionCallbackHandler->handle($payload);
        } catch (Throwable $e) {
            Log::add('Subscription callback error: ' . $e->getMessage(), Log::ERROR, 'alliance_payment');
            // Non-2xx lets the provider redeliver; the handler is idempotent, so that is safe.
            http_response_code(500);

            return true;
        }

        if (!$result->handled) {
            return false;
        }

        $newStatus = match ($result->vmOrderStatus) {
            CallbackResult::ORDER_STATUS_SUCCESS => $this->allianceConfig->getSuccessPaymentStatus(),
            CallbackResult::ORDER_STATUS_FAIL => $this->allianceConfig->getFailPaymentStatus(),
            default => '',
        };

        if ($newStatus !== '') {
            VmModel::getModel('orders')->updateStatusForOneOrder(
                $result->subscription->virtuemartOrderId,
                ['order_status' => $newStatus],
                true
            );
        }

        return true;
    }

    /**
     *
     * @return array|null
     *
     * @since version 1.5.0
     */
    private function readCallbackData(): ?array
    {
        $raw = file_get_contents('php://input');

        Log::add(
            'Webhook JWS raw data handler: ' . print_r($raw, true),
            Log::INFO,
            'alliance_payment'
        );

        $trimmed = trim((string) $raw);
        $isToken = $trimmed !== '' && !str_contains($trimmed, ' ');

        if ($isToken && substr_count($trimmed, '.') === 2) {
            try {
                return $this->jwsVerifier->verify($trimmed);
            } catch (Throwable $e) {
                Log::add(
                    'Webhook JWS verification failed: ' . $e->getMessage(),
                    Log::ERROR,
                    'alliance_payment'
                );

                return null;
            }
        }

        $data = json_decode($raw, true);

        Log::add(
            'Webhook JWS data handler: ' . print_r($data, true),
            Log::INFO,
            'alliance_payment'
        );

        return is_array($data) ? $data : null;
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
        // A subscription order has no row in jm_virtuemart_payment_plg_alliance_order — it lives
        // entirely in jm_virtuemart_payment_plg_alliance_subscription instead (see
        // SubscriptionOrderProcessor) — so it must be shown from that data, not the one-off table
        // (which load() would just leave empty, showing "no transactions" for every subscription).
        $subscription = $this->subscriptionRepository->findByVirtuemartOrderId($orderId);

        if ($subscription !== null) {
            return $this->showSubscriptionOrderBE($subscription);
        }

        $allianceOrder = $this->allianceOrderTable->load($orderId, 'order_id');
        $callbackData = json_decode($allianceOrder->callback_data, true);
        $operations = $callbackData['operations'] ?? [];
        $transactions = [];

        $conversionRate = $allianceOrder->conversion_rate !== null
            ? (float) $allianceOrder->conversion_rate
            : null;

        $currencyName = null;
        if ($allianceOrder->currency_code !== null) {
            $currencyName = $this->allianceConfig->getCurrencyNameByNumericCode(
                (int) $allianceOrder->currency_code
            );
        }

        $hasForeignCurrency = $conversionRate > 1.0 && $currencyName !== null;

        foreach ($operations as $operation) {
            $coinAmountUah = (int) $operation['coinAmount'];
            $priceUAH      = round($coinAmountUah / 100, 2);

            $tx = [
                'type' => $operation['type'],
                'operationId' => $operation['operationId'] ?? '',
                'status' => $operation['status'],
                'creationDateTime' => $operation['creationDateTime'],
                'priceUAH' => $priceUAH,
                'priceUSD' => null,
                'priceEUR' => null,
            ];

            if ($hasForeignCurrency) {
                $foreignAmount = round($coinAmountUah / $conversionRate / 100, 2);
                if ($currencyName === 'USD') {
                    $tx['priceUSD'] = $foreignAmount;
                } elseif ($currencyName === 'EUR') {
                    $tx['priceEUR'] = $foreignAmount;
                }
            }

            $transactions[] = $tx;
        }

        $html = $this->renderByLayout('callback_info', [
            'transactions' => $transactions,
            'hppOrderId' => $allianceOrder->hpp_order_id,
        ]);

        $orderModel = VmModel::getModel('orders');
        $order = $orderModel->getOrder($orderId);
        $isFullRefundAllowed = true;
        $isRefundAllowed = true;

        foreach ($order['items'] as $item) {
            if ($item->order_status === $this->allianceConfig->getSuccessRefundStatus()) {
                $isFullRefundAllowed = false;
                break;
            }
        }

        if ($allianceOrder->transaction_type === 102 && $allianceOrder->hpp_pay_type === Config::HPP_PAY_TYPE_A2A) {
            $isRefundAllowed = false;
        }

        $viewData = new stdClass();
        $viewData->order_id = $orderId;
        $viewData->items = $order['items'];
        $viewData->fullRefundTriger = self::FULL_REFUND_TRIGGER;
        $viewData->isFullRefundAllowed = $isFullRefundAllowed;
        $viewData->isRefundAllowed = $isRefundAllowed;
        $viewData->partialRefundTrigger = self::PARTIAL_REFUND_TRIGGER;

        $html .= $this->renderByLayout('refund_form', (array)$viewData);

        return $html;
    }

    /**
     * Backend order-view "transactions history" for a subscription order, from LOCAL data only
     * (jm_virtuemart_payment_plg_alliance_scheduled_payment) — same as the one-off branch above,
     * which never calls the provider on page render either; fresh data is pulled in via the
     * "Check order status" button (checkAllianceOrderStatus() below -> PaymentProcessor::
     * updateOrderData(), fixed to handle subscriptions too) or real webhooks, both of which already
     * write through ScheduledPaymentRepository via SubscriptionCallbackHandler.
     *
     * Only the "Check order status" block of refund_form.php is reused (via check_status_form.php),
     * never the partial/full refund UI: refunds there are a one-off-order concept keyed off
     * AllianceOrderTable's transaction_type/hpp_pay_type, which a subscription order never has, and
     * RefundProcessor does not know about subscriptions either; subscriptions are cancelled instead
     * (see plgVmOnShowOrderFEPayment / SubscriptionCancelService).
     */
    private function showSubscriptionOrderBE(Subscription $subscription): string
    {
        $payments = $this->scheduledPaymentRepository->findBySubscription($subscription->id);
        $paymentsByOperation = [];

        foreach ($payments as $payment) {
            if (!empty($payment['operation_id'])) {
                $paymentsByOperation[$payment['operation_id']] = $payment;
            }
        }

        $transactions = [];
        $listedOperations = [];

        // Every operation the provider has reported (payments, retries, refunds), accumulated from callbacks
        // and from the "Check order status" refresh.
        foreach ($subscription->callbackData['operations'] ?? [] as $operation) {
            $operationId = (string) ($operation['operationId'] ?? '');
            $payment = $paymentsByOperation[$operationId] ?? null;
            $listedOperations[$operationId] = true;

            $type = (string) ($operation['type'] ?? '');
            $number = $payment['payment_number'] ?? null;
            $label = $type !== '' ? $type : 'PAYMENT';

            if ($number !== null) {
                $label .= ' (#' . $number . ')';
            }

            $transactions[] = [
                'type' => $label,
                'operationId' => $operationId,
                'status' => (string) ($operation['status'] ?? $payment['status'] ?? ''),
                'creationDateTime' => (string) ($operation['creationDateTime'] ?? $payment['executed_at'] ?? ''),
                'priceUAH' => round(((int) ($operation['coinAmount'] ?? $payment['coin_amount'] ?? 0)) / 100, 2),
                'priceUSD' => null,
                'priceEUR' => null,
                'note' => $this->buildPaymentNote($payment),
            ];
        }

        // Local rows with no operation yet (scheduled/waiting) or stored before operations were accumulated.
        foreach ($payments as $payment) {
            $operationId = (string) ($payment['operation_id'] ?? '');

            if ($operationId !== '' && isset($listedOperations[$operationId])) {
                continue;
            }

            $transactions[] = [
                'type' => Text::sprintf('VMPAYMENT_ALLIANCE_CB_PAYMENT_NUMBER', $payment['payment_number'] ?? '?'),
                'operationId' => $operationId,
                'status' => (string) $payment['status'],
                'creationDateTime' => (string) ($payment['executed_at'] ?? $payment['scheduled_date'] ?? ''),
                'priceUAH' => round(((int) ($payment['coin_amount'] ?? 0)) / 100, 2),
                'priceUSD' => null,
                'priceEUR' => null,
                'note' => $this->buildPaymentNote($payment),
            ];
        }

        $html = $this->renderByLayout('callback_info', [
            'transactions' => $transactions,
            'hppOrderId' => (string) $subscription->providerOrderId,
            'subscriptionStatus' => $subscription->status->value,
        ]);

        $html .= $this->renderByLayout('check_status_form', [
            'order_id' => $subscription->virtuemartOrderId,
        ]);

        return $html;
    }

    /**
     * @param array|null $payment
     * @return string
     */
    private function buildPaymentNote(?array $payment): string
    {
        if ($payment === null) {
            return '';
        }

        $parts = [];

        if (!empty($payment['scheduled_payment_id'])) {
            $parts[] = Text::sprintf('VMPAYMENT_ALLIANCE_CB_SCHEDULED_ID', $payment['scheduled_payment_id']);
        }

        if (!empty($payment['scheduled_date'])) {
            $parts[] = Text::sprintf('VMPAYMENT_ALLIANCE_CB_SCHEDULED_FOR', $payment['scheduled_date']);
        }

        if (!empty($payment['retry_count'])) {
            $parts[] = Text::sprintf('VMPAYMENT_ALLIANCE_CB_RETRIES', (int) $payment['retry_count']);
        }

        if (!empty($payment['needs_review'])) {
            $parts[] = Text::sprintf('VMPAYMENT_ALLIANCE_CB_NEEDS_REVIEW', $payment['review_note'] ?? '');
        }

        return implode(' | ', $parts);
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
     * Starts a subscription checkout: provider subscription order + redirect to the Hosted Payment Page.
     *
     * @param array $order
     *
     * @return bool
     *
     * @throws Exception
     * @since version 1.5.0
     */
    private function confirmSubscriptionOrder(array $order): bool
    {
        $app = Factory::getApplication();
        $result = $this->subscriptionOrderProcessor->process($order);

        if (!empty($result['redirectUrl'])) {
            $app->redirect($result['redirectUrl']);

            return true;
        }

        $error = (string) ($result['error'] ?? '');
        $message = !empty($result['message'])
            ? (string) $result['message']
            : Text::_(CheckoutGuard::messageKey($error !== '' ? $error : 'provider'));

        $app->enqueueMessage($message, 'error');

        return false;
    }

    /**
     * Cart-level safety net (besides add-to-cart): re-checks the subscription rules when the customer
     * proceeds to payment, and requires AlliancePay as the payment method for a subscription cart.
     *
     * @param VirtueMartCart $cart
     *
     * @return bool|null false to send the customer back to the payment step; null when not applicable
     *
     * @since version 1.5.0
     */
    public function plgVmOnCheckoutCheckDataPayment(VirtueMartCart $cart)
    {
        $inspection = CartInspection::fromLines($cart->cartProductsData ?? []);

        if (!$inspection->hasSubscription()) {
            return null;
        }

        $userId = (int) Factory::getApplication()->getIdentity()->id;
        $violation = $this->checkoutGuard->check($inspection, $userId);

        if ($violation === null && !$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
            $violation = 'requires_alliancepay';
        }

        if ($violation === null) {
            return null;
        }

        Factory::getApplication()->enqueueMessage(Text::_(CheckoutGuard::messageKey($violation)), 'warning');

        return false;
    }

    /**
     * Shows the customer's subscription (status + cancel button) on the order details page.
     *
     * @param int $virtuemart_order_id
     * @param int $virtuemart_paymentmethod_id
     * @param string $payment_name
     *
     * @return null
     *
     * @since version 1.5.0
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        if (!$this->selectedThisByMethodId($virtuemart_paymentmethod_id)) {
            return null;
        }

        $subscription = $this->subscriptionRepository->findByVirtuemartOrderId((int) $virtuemart_order_id);

        if ($subscription === null || Factory::getApplication()->input->getCmd('format') === 'pdf') {
            return null;
        }

        $method = $this->getVmPluginMethod($virtuemart_paymentmethod_id);
        $canCancel = in_array($subscription->status->value, ['ACTIVE', 'PENDING'], true)
            && $subscription->providerOrderId !== null;

        $payment_name = ($method->payment_name ?? '') . $this->renderByLayout('subscription_order', [
            'subscription' => $subscription,
            'canCancel' => $canCancel,
            'cancelUrl' => Route::_(
                'index.php?option=com_virtuemart&view=plugin&type=vmpayment&name=alliancepay'
                . '&task=' . self::SELF_CALL_CANCEL_SUBSCRIPTION . '&format=raw',
                false
            ),
            'token' => Session::getFormToken(),
        ]);

        return null;
    }

    /**
     * Storefront actions of the plugin (VM plugin controller): cancelling one's own subscription.
     *
     * @param string $type
     * @param string $name
     * @param mixed $render
     *
     * @return bool
     *
     * @throws Exception
     * @since version 1.5.0
     */
    public function plgVmOnSelfCallFE($type, $name, &$render)
    {
        if ($name !== $this->_name || $type !== 'vmpayment') {
            return false;
        }

        $app = Factory::getApplication();
        $input = $app->input;

        if ($input->getCmd('task') !== self::SELF_CALL_CANCEL_SUBSCRIPTION) {
            return false;
        }

        $orderUrl = Route::_('index.php?option=com_virtuemart&view=orders&layout=list', false);

        if ($input->getMethod() !== 'POST' || !Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect($orderUrl);
        }

        $userId = (int) $app->getIdentity()->id;

        try {
            $this->subscriptionCancelService->cancelByCustomer($input->post->getInt('subscription_id'), $userId);
            $app->enqueueMessage(Text::_('VMPAYMENT_ALLIANCEPAY_SUB_CANCELED'), 'message');
        } catch (Throwable $e) {
            Log::add('Subscription cancel failed: ' . $e->getMessage(), Log::ERROR, 'alliance_payment');
            $app->enqueueMessage(Text::_('VMPAYMENT_ALLIANCEPAY_SUB_CANCEL_FAILED'), 'error');
        }

        $app->redirect($orderUrl);

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
