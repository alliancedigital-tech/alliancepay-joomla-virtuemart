<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use VmTable;

defined('_JEXEC') or die();

/**
 * @package     Alliance\Plugin\Vmpayment\Alliancepay\Extension\Entity
 *
 * @since version 1.0.0
 */
class AllianceAuthenticationData extends VmTable
{
    public $id = 0;
    public $virtuemart_paymentmethod_id = 0;
    public $device_id = '';
    public $refresh_token = '';
    public $auth_token = '';
    public $server_public = '';
    public $token_expiration_date_time = '0000-00-00 00:00:00';
    public $token_expiration = '0000-00-00 00:00:00';
    public $session_expiration = '0000-00-00 00:00:00';

    public function __construct(&$db) {
        if (!$db) {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
        }
        parent::__construct('#__virtuemart_payment_plg_alliance_auth_data', 'id', $db);
    }
}
