<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

namespace Alliance\Component\Alliancepay\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 * Turns the plugin's machine-readable validation errors into messages the administrator can act on.
 *
 * @since 1.5.0
 */
final class ErrorFormatter
{
    /**
     * @param array $errors
     * @return array
     */
    public static function format(array $errors): array
    {
        $messages = [];

        foreach ($errors as $error) {
            $field = Text::_('COM_ALLIANCEPAY_FIELD_' . strtoupper((string) $error['field']));
            $problem = Text::_('COM_ALLIANCEPAY_ERR_' . strtoupper((string) $error['code']));
            $messages[] = $error['row'] >= 0
                ? Text::sprintf('COM_ALLIANCEPAY_ERR_ROW', $error['row'] + 1, $field, $problem)
                : $field . ': ' . $problem;
        }

        return array_values(array_unique($messages));
    }
}
