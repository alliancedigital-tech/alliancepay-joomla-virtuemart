<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

defined('_JEXEC') or die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;

class plgVmCustomAlliancepay_subscription extends vmCustomPlugin
{
    private const SERVICE_NS = 'Alliance\\Plugin\\Vmpayment\\Alliancepay\\';

    private static ?\Joomla\DI\Container $container = null;

    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);

        $varsToPush = [
            'show_plan_details' => [1, 'bool'],
        ];

        $this->setConfigParameterable('customfield_params', $varsToPush);
    }

    /**
     * @param $field
     * @param $product_id
     * @param $row
     * @param $retValue
     * @return string|true
     */
    public function plgVmOnProductEdit($field, $product_id, &$row, &$retValue)
    {
        if ($field->custom_element != $this->_name) {
            return '';
        }

        $link = 'index.php?option=com_alliancepay&view=plans&filter[product_id]=' . (int) $product_id;

        $retValue .= '<fieldset><legend>' . vmText::_('PLG_VMCUSTOM_ALLIANCEPAY_SUBSCRIPTION') . '</legend>'
            . '<table class="admintable">'
            . VmHTML::row(
                'select',
                'PLG_VMCUSTOM_ALLIANCEPAY_SUBSCRIPTION_SHOW_PLAN_DETAILS',
                'customfield_params[' . $row . '][show_plan_details]',
                [0 => vmText::_('COM_VIRTUEMART_NO'), 1 => vmText::_('COM_VIRTUEMART_YES')],
                $field->show_plan_details ?? 1,
                '',
                'value',
                'text',
                false
            )
            . '<tr><td colspan="2"><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" target="_blank">'
            . vmText::_('PLG_VMCUSTOM_ALLIANCEPAY_SUBSCRIPTION_MANAGE_PLANS') . '</a></td></tr>'
            . '</table></fieldset>';
        $row++;

        return true;
    }

    /**
     * @param $product
     * @param $group
     * @return string|true
     */
    public function plgVmOnDisplayProductFEVM3(&$product, &$group)
    {
        if ($group->custom_element != $this->_name) {
            return '';
        }

        $services = $this->services();

        if ($services === null) {
            return true;
        }

        $plans = $services->get(self::SERVICE_NS . 'Subscription\\Repository\\PlanRepository')
            ->findOfferableForVariant((int) $product->virtuemart_product_id);
        $config = $services->get(self::SERVICE_NS . 'Config\\Config');

        if ($plans === [] || !$config->isSubscriptionsEnabled()) {
            return true;
        }

        $group->display .= $this->renderByLayout('default', [
            'product' => $product,
            'group' => $group,
            'plans' => $plans,
            'formatter' => $services->get(self::SERVICE_NS . 'Subscription\\Domain\\PlanFormatter'),
            'isGuest' => (int) Factory::getApplication()->getIdentity()->id === 0,
        ]);

        return true;
    }

    /**
     * @param $product
     * @param $customfield
     * @param $customProductData
     * @param $customFiltered
     * @return false
     */
    public function plgVmOnAddToCartFilter(&$product, &$customfield, &$customProductData, &$customFiltered)
    {
        if (($customfield->custom_element ?? '') != $this->_name) {
            return false;
        }

        if (!$this->classLoaded()) {
            Log::add(
                'plgVmOnAddToCartFilter: plugin classes unavailable, subscription selection for product #'
                . (int) ($product->virtuemart_product_id ?? 0) . ' cannot be processed and will be dropped',
                Log::ERROR,
                'alliance_payment'
            );

            return false;
        }

        $selected = $customProductData[$customfield->virtuemart_custom_id][$customfield->virtuemart_customfield_id] ?? null;
        $planId = \Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Checkout\SubscriptionSelection::extractPlanId(
            [[$selected]]
        );

        if ($planId === null) {
            return false;
        }

        $violation = $this->guard()?->check(
            new \Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Checkout\CartInspection(
                [['productId' => (int) $product->virtuemart_product_id, 'planId' => $planId, 'quantity' => 1]],
                0
            ),
            (int) Factory::getApplication()->getIdentity()->id
        );

        if ($violation !== null) {
            Log::add(
                'plgVmOnAddToCartFilter: subscription for product #' . (int) $product->virtuemart_product_id
                . ' plan #' . $planId . ' rejected by CheckoutGuard: ' . $violation,
                Log::ERROR,
                'alliance_payment'
            );
            vmWarn(Text::_(\Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Checkout\CheckoutGuard::messageKey($violation)));
            $product->remove = true;

            return false;
        }

        $customProductData[$customfield->virtuemart_custom_id][$customfield->virtuemart_customfield_id] = $selected;
        $customFiltered = true;

        return false;
    }

    /**
     * @param $cart
     * @return void
     */
    public function plgVmOnAddToCart(&$cart)
    {
        if (!$this->classLoaded()) {
            return;
        }

        $inspection = \Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Checkout\CartInspection::fromLines(
            $cart->cartProductsData ?? []
        );

        if (!$inspection->hasSubscription()) {
            return;
        }

        $mixed = $inspection->regularCount > 0 || count($inspection->subscriptions) > 1;

        if ($mixed) {
            $lastId = (int) ($cart->lastAddedProduct ?? 0);

            for ($i = count($cart->cartProductsData) - 1; $i >= 0; $i--) {
                if ((int) ((array) $cart->cartProductsData[$i])['virtuemart_product_id'] === $lastId) {
                    unset($cart->cartProductsData[$i]);
                    $cart->cartProductsData = array_values($cart->cartProductsData);
                    break;
                }
            }

            $key = $inspection->regularCount > 0
                ? \Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Checkout\CheckoutGuard::VIOLATION_MIXED_CART
                : \Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Checkout\CheckoutGuard::VIOLATION_MULTIPLE_SUBSCRIPTIONS;
            vmWarn(Text::_(\Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Checkout\CheckoutGuard::messageKey($key)));

            return;
        }

        foreach ($cart->cartProductsData as $index => $line) {
            $line = (array) $line;

            if ((int) ($line['quantity'] ?? 1) !== 1) {
                $line['quantity'] = 1;
                $cart->cartProductsData[$index] = $line;
                vmWarn(Text::_('VMPAYMENT_ALLIANCEPAY_SUB_ERR_QUANTITY'));
            }
        }
    }

    public function plgVmOnViewCartVM3(&$product, &$productCustom, &$html)
    {
        if (empty($productCustom->custom_element) || $productCustom->custom_element != $this->_name) {
            return false;
        }

        $selected = $product->customProductData[$productCustom->virtuemart_custom_id][$productCustom->virtuemart_customfield_id] ?? null;

        if (!is_array($selected) || ($selected['mode'] ?? '') !== 'subscription') {
            return false;
        }

        $planName = '';
        $services = $this->services();

        if ($services !== null) {
            $plan = $services->get(self::SERVICE_NS . 'Subscription\\Repository\\PlanRepository')->find((int) ($selected['plan_id'] ?? 0));
            $planName = $plan ? $plan->name : '';
        }

        $html .= '<span>' . htmlspecialchars(Text::_('PLG_VMCUSTOM_ALLIANCEPAY_SUBSCRIPTION_IN_CART'), ENT_QUOTES, 'UTF-8')
            . ' ' . htmlspecialchars($planName, ENT_QUOTES, 'UTF-8') . '</span>';

        return true;
    }

    public function plgVmOnViewCartModuleVM3(&$product, &$productCustom, &$html)
    {
        return $this->plgVmOnViewCartVM3($product, $productCustom, $html);
    }

    public function plgVmDisplayInOrderBEVM3(&$product, &$productCustom, &$html)
    {
        $this->plgVmOnViewCartVM3($product, $productCustom, $html);
    }

    public function plgVmDisplayInOrderFEVM3(&$product, &$productCustom, &$html)
    {
        $this->plgVmOnViewCartVM3($product, $productCustom, $html);
    }

    /**
     * @param $plgType
     * @param $data
     * @param $table
     * @return false|void
     */
    public function plgVmOnStoreInstallPluginTable($plgType, $data, $table)
    {
        if ($plgType != $this->_psType) {
            return false;
        }

        if (!empty($data['custom_element']) && $data['custom_element'] != $this->_name) {
            return false;
        }

        $this->onStoreInstallPluginTable($plgType, $data['custom_element']);

        $needsStore = false;

        if (empty($table->is_input)) {
            vmInfo('COM_VIRTUEMART_CUSTOM_IS_CART_INPUT_SET');
            $table->is_input = 1;
            $needsStore = true;
        }

        if (empty($table->layout_pos)) {
            $table->layout_pos = 'addtocart';
            $needsStore = true;
        }

        if ($needsStore) {
            $table->store();
        }
    }

    public function plgVmDeclarePluginParamsCustomVM3(&$data)
    {
        return $this->declarePluginParams('custom', $data);
    }

    public function plgVmGetTablePluginParams($psType, $name, $id, &$xParams, &$varsToPush)
    {
        return $this->getTablePluginParams($psType, $name, $id, $xParams, $varsToPush);
    }

    public function plgVmSetOnTablePluginParamsCustom($name, $id, &$table, $xParams)
    {
        return $this->setOnTablePluginParams($name, $id, $table, $xParams);
    }

    public function plgVmOnDisplayEdit($virtuemart_custom_id, &$customPlugin)
    {
        return $this->onDisplayEditBECustom($virtuemart_custom_id, $customPlugin);
    }

    public function plgVmOnSelfCallFE($type, $name, &$render)
    {
        if ($name != $this->_name || $type != 'vmcustom') {
            return false;
        }

        $render = new stdClass();
        $render->html = '';
    }

    /**
     * @return bool
     */
    private function classLoaded(): bool
    {
        if (!class_exists(self::SERVICE_NS . 'Services\\Bootstrap\\ServiceRegistry')) {
            $autoload = JPATH_PLUGINS . '/vmpayment/alliancepay/vendor/autoload.php';

            if (is_file($autoload)) {
                require_once $autoload;
            } else {
                Log::add(
                    'alliancepay_subscription: vmpayment/alliancepay autoload.php not found at ' . $autoload
                    . ' — is the AlliancePay payment plugin installed?',
                    Log::WARNING,
                    'alliance_payment'
                );
            }
        }

        $loaded = class_exists(self::SERVICE_NS . 'Services\\Bootstrap\\ServiceRegistry');

        if (!$loaded) {
            Log::add(
                'alliancepay_subscription: ServiceRegistry class still not available after autoload attempt',
                Log::WARNING,
                'alliance_payment'
            );
        }

        return $loaded;
    }

    private function services(): ?\Joomla\DI\Container
    {
        if (self::$container !== null) {
            return self::$container;
        }

        if (!$this->classLoaded()) {
            return null;
        }

        try {
            self::$container = (self::SERVICE_NS . 'Services\\Bootstrap\\ServiceRegistry')::createContainer();
        } catch (\Throwable $e) {
            Log::add('alliancepay_subscription: ' . $e->getMessage(), Log::ERROR, 'alliance_payment');

            return null;
        }

        return self::$container;
    }

    private function guard(): ?object
    {
        return $this->services()?->get(self::SERVICE_NS . 'Subscription\\Checkout\\CheckoutGuard');
    }
}
