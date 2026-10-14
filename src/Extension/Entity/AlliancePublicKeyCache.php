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
 * @since version 1.5.0
 */
class AlliancePublicKeyCache extends VmTable
{
    public $id = 0;
    public $kid = '';
    public $jwk = '';
    public $expires_at = '0000-00-00 00:00:00';

    public function __construct(&$db) {
        if (!$db) {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
        }
        parent::__construct('#__virtuemart_payment_plg_alliance_pubkey_cache', 'id', $db);
    }
}
