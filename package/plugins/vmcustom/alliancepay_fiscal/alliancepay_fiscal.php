<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

defined('_JEXEC') or die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;

/**
 * Product-level fiscal attributes for AlliancePay fiscalization (taxCode, classificationCode,
 * exciseLabels, measurementCode). Backend only: nothing is rendered on the storefront, in the cart or
 * in the customer's order. The values are read by the payment plugin
 * (Fiscalization\Service\ProductFiscalAttributeService); an empty field falls back to the defaults in
 * the payment method settings.
 *
 * @since version 1.6.0
 */
class plgVmCustomAlliancepay_fiscal extends vmCustomPlugin
{
    private const VALIDATOR = 'Alliance\\Plugin\\Vmpayment\\Alliancepay\\Fiscalization\\Domain\\FiscalValueValidator';

    /** Parameter name => language key of its label. The names equal FiscalValueValidator::FIELD_*. */
    private const FIELDS = [
        'tax_code' => 'PLG_VMCUSTOM_ALLIANCEPAY_FISCAL_TAX_CODE',
        'classification_code' => 'PLG_VMCUSTOM_ALLIANCEPAY_FISCAL_CLASSIFICATION_CODE',
        'excise_labels' => 'PLG_VMCUSTOM_ALLIANCEPAY_FISCAL_EXCISE_LABELS',
        'measurement_code' => 'PLG_VMCUSTOM_ALLIANCEPAY_FISCAL_MEASUREMENT_CODE',
    ];

    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);

        $varsToPush = [];

        foreach (array_keys(self::FIELDS) as $name) {
            $varsToPush[$name] = ['', 'char'];
        }

        $this->setConfigParameterable('customfield_params', $varsToPush);
    }

    /**
     * Product form: the four fiscal fields.
     *
     * @param $field
     * @param $product_id
     * @param $row
     * @param $retValue
     *
     * @return string|true
     */
    public function plgVmOnProductEdit($field, $product_id, &$row, &$retValue)
    {
        if ($field->custom_element != $this->_name) {
            return '';
        }

        $html = '<fieldset><legend>' . vmText::_('PLG_VMCUSTOM_ALLIANCEPAY_FISCAL') . '</legend>'
            . '<table class="admintable">';

        foreach (self::FIELDS as $name => $labelKey) {
            $inputName = 'customfield_params[' . $row . '][' . $name . ']';
            $value = htmlspecialchars((string) ($field->{$name} ?? ''), ENT_QUOTES, 'UTF-8');

            $input = $name === 'excise_labels'
                ? '<textarea class="inputbox" name="' . $inputName . '" rows="3" cols="40">' . $value . '</textarea>'
                : '<input type="text" class="inputbox" name="' . $inputName . '" value="' . $value
                    . '" size="20" maxlength="32" />';

            $html .= '<tr><td class="key hasTooltip" title="' . htmlspecialchars(vmText::_($labelKey . '_TIP'), ENT_QUOTES, 'UTF-8')
                . '">' . vmText::_($labelKey) . '</td><td>' . $input . '</td></tr>';
        }

        $retValue .= $html . '</table><p>' . vmText::_('PLG_VMCUSTOM_ALLIANCEPAY_FISCAL_HINT') . '</p></fieldset>';
        $row++;

        return true;
    }

    /**
     * Called by VirtueMart after the product's custom fields were stored. The save cannot be blocked from
     * here, so invalid values are reported; the payment plugin treats an invalid stored value as "not set"
     * and falls back to the default.
     *
     * @param $data
     * @param $plugin_param
     * @param $old_customfield_ids
     * @param $key
     *
     * @return bool
     */
    public function plgVmOnStoreProduct($data, $plugin_param, $old_customfield_ids, $key)
    {
        if (!is_array($plugin_param) || !$this->validatorLoaded()) {
            return true;
        }

        $raw = [];

        foreach (array_keys(self::FIELDS) as $name) {
            $value = $plugin_param[$name] ?? '';
            $raw[$name] = is_scalar($value) ? (string) $value : '';
        }

        Factory::getApplication()->getLanguage()->load(
            'plg_vmpayment_alliancepay',
            JPATH_PLUGINS . '/vmpayment/alliancepay'
        );

        // Product level: an empty measurement code is fine (the default from the settings is used).
        foreach ((self::VALIDATOR)::validate($raw, false) as $errorKey) {
            vmWarn(Text::_($errorKey));
        }

        return true;
    }

    /**
     * @param $plgType
     * @param $data
     * @param $table
     *
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

        // Backend-only field: keep it off the storefront.
        if (empty($table->admin_only)) {
            $table->admin_only = 1;
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

    private function validatorLoaded(): bool
    {
        if (!class_exists(self::VALIDATOR)) {
            $autoload = JPATH_PLUGINS . '/vmpayment/alliancepay/vendor/autoload.php';

            if (is_file($autoload)) {
                require_once $autoload;
            }
        }

        if (!class_exists(self::VALIDATOR)) {
            Log::add(
                'alliancepay_fiscal: the AlliancePay payment plugin (vmpayment/alliancepay) is not available; '
                . 'fiscal values are not validated',
                Log::WARNING,
                'alliance_payment'
            );

            return false;
        }

        return true;
    }
}
