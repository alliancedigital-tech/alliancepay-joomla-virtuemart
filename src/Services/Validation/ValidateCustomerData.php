<?php
/**
 * @package     Alliance\Plugin\Vmpayment\Alliancepay\Services\Validation
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Alliance\Plugin\Vmpayment\Alliancepay\Services\Validation;

/**
 * @package     Alliance\Plugin\Vmpayment\Alliancepay\Services\Validation
 *
 * @since version 1.1.0
 */
class ValidateCustomerData
{
    const CUSTOMER_DATA_RULES = [
        'senderCustomerId' =>
            ['type' => 'string', 'max_len' => 255, 'required' => true],
        'senderFirstName' => [
            'type' => 'string',
            'max_len' => 30,
            'required' => false,
            'no_only_digits' => true,
            'pattern' =>
                '/^[a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ]([a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ\s\-\']*[a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ])?$/u',
            'stop_words' => [
                'NULL',
                '3D SECURE',
                'SURNAME',
                'CARDHOLDER',
                'UNKNOWN'
            ]
        ],
        'senderLastName' => [
            'type' => 'string',
            'max_len' => 30,
            'required' => false,
            'no_only_digits' => true,
            'pattern' =>
                '/^[a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ]([a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ\s\-\']*[a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ])?$/u'
        ],
        'senderMiddleName' => [
            'type' => 'string',
            'max_len' => 30,
            'required' => false,
            'no_only_digits' => true,
            'pattern' =>
                '/^[a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ]([a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ\s\-\']*[a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ])?$/u'
        ],
        'senderEmail' => [
            'type' => 'string',
            'max_len' => 256,
            'required' => false
        ],
        'senderCountry' => [
            'type' => 'string',
            'max_len' => 3,
            'required' => false
        ],
        'senderRegion' => [
            'type' => 'string',
            'max_len' => 255,
            'required' => false
        ],
        'senderCity' => [
            'type' => 'string',
            'max_len' => 25,
            'required' => false
        ],
        'senderStreet' => [
            'type' => 'string',
            'max_len' => 35,
            'required' => false
        ],
        'senderAdditionalAddress' => [
            'type' => 'string',
            'max_len' => 255,
            'required' => false
        ],
        'senderIp' => [
            'type' => 'ip'
        ],
        'senderPhone' => [
            'type' => 'numeric_string',
            'max_len' => 20,
            'required' => false
        ],
        'senderZipCode' => [
            'type' => 'string',
            'max_len' => 50,
            'required' => false
        ],
    ];

    /**
     * @param array $data
     *
     * @return array
     *
     * @since version 1.1.0
     */
    public function validateAndClenUpData(array $data): array
    {

        $validatedData = [];

        foreach (self::CUSTOMER_DATA_RULES as $field => $rules) {
            $value = $data[$field] ?? null;

            if (empty($value) && $value !== '0') {
                if ($rules['required']) {
                    continue;
                }
                continue;
            }

            if ($rules['type'] === 'ip') {
                if (!filter_var($value, FILTER_VALIDATE_IP)) {
                    continue;
                }
            }

            if ($rules['type'] === 'numeric_string') {
                $value = preg_replace('/[^0-9]/', '', (string)$value);
            } else {
                $value = trim((string)$value);
            }

            if (!empty($rules['no_only_digits']) && ctype_digit($value)) {
                continue;
            }

            if (!empty($rules['stop_words'])) {
                $upperValue = mb_strtoupper($value);
                foreach ($rules['stop_words'] as $stopWord) {
                    if (str_contains($upperValue, mb_strtoupper($stopWord))) {
                        $value = '';
                        break;
                    }
                }
                if (empty($value)) continue;
            }

            if (!empty($rules['pattern'])) {
                if (!preg_match($rules['pattern'], $value)) {
                    continue;
                }
            }

            if (mb_strlen($value) > $rules['max_len']) {
                $value = mb_substr($value, 0, $rules['max_len']);
            }

            $validatedData[$field] = $value;
        }

        return $validatedData;
    }
}
