<?php
/**
 * @package     Alliance\Plugin\Vmpayment\Alliancepay\Services\Url
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Alliance\Plugin\Vmpayment\Alliancepay\Services\Url;

use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

class UrlProvider
{
    /**
     * @param $order
     *
     * @return string
     *
     * @since version 1.0.0
     */
    public function getSuccessUrl($order): string
    {
        $url = '';

        if (!empty($order['details']['BT'])) {
            $orderId = $order['details']['BT']->virtuemart_order_id;
            $path = '/index.php?option=com_virtuemart&view=pluginresponse'
                . '&task=pluginresponsereceived&pm=alliancepay&oid=' . $orderId;

            $url = Uri::root(
                false,
                $path
            );
        }

        return $url;
    }

    /**
     * @param $order
     *
     * @return string
     *
     * @since version 1.0.0
     */
    public function getFailUrl($order): string
    {
        $url = '';

        if (!empty($order['details']['BT'])) {
            $orderId = $order['details']['BT']->virtuemart_order_id;
            $path = '/option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on='
                . $orderId . '&pm=alliancepay&action=cancel';

            $url = Uri::root(
                false,
                $path
            );
        }

        return $url;
    }

    /**
     * @param int $orderId
     * @param array $params
     *
     * @return string
     *
     * @since version 1.0.2
     */
    public function getCallbackUrl(int $orderId, array $params = []): string
    {
        $url = '';

        if (!empty($orderId)) {
            $path= '/index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&action=callback&onme='
                . $orderId .'&peityp=alliancepay';

            foreach ($params as $key => $value) {
                $path .= '&' . $key . '=' . $value;
            }

            $url = Uri::root(
                false,
                $path
            );
        }

        return $url;
    }
}
