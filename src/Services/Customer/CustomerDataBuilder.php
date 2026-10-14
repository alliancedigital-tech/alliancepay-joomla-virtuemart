<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Services\Customer;

use Alliance\Plugin\Vmpayment\Alliancepay\Services\Validation\ValidateCustomerData;
use VmModel;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Services\Customer
 *
 * @since version 1.5.0
 */
class CustomerDataBuilder
{
    public function __construct(private readonly ValidateCustomerData $validateCustomerData)
    {
    }

    /**
     * @param $order
     *
     * @return array
     *
     * @since version 1.0.0
     */
    public function build($order): array
    {
        $data = [];

        if (empty($order['details']['BT'])) {
            return $data;
        }

        $data['senderCustomerId'] = $order['details']['BT']->customer_number;
        $bt = $order['details']['BT'];
        $countryModel = VmModel::getModel('country');
        $stateModel = VmModel::getModel('state');
        $country = $countryModel->getData($bt->virtuemart_country_id);
        $state = $stateModel->getData($bt->virtuemart_state_id);

        $data['senderEmail'] = $bt->email ?? '';
        $data['senderFirstName'] = $bt->first_name ?? '';
        $data['senderLastName'] = $bt->last_name ?? '';
        $data['senderMiddleName'] = $bt->middle_name ?? '';
        $data['senderPhone'] = $bt->phone_1 ?: ($bt->phone_2 ?: '');
        $data['senderCity'] = $bt->city ?? '';
        $data['senderZipCode'] = $bt->zip ?? '';
        $data['senderStreet'] = $bt->address_1 ?? '';
        $data['senderAdditionalAddress'] = $bt->address_2 ?? '';
        $data['senderCountry'] = $country->country_num_code ?? '';
        $data['senderRegion'] = $state->state_name ?? '';
        $data['senderIp'] = $bt->ip_address ?? '';

        return $this->validateCustomerData->validateAndClenUpData($data);
    }
}
