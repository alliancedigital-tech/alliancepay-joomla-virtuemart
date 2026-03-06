<?php
/**
 *
 */

namespace Alliance\Plugin\Vmpayment\Alliancepay\Field;

defined('_JEXEC') or die;

use Exception;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

class AllianceAuthField extends FormField
{
    protected $type = 'AllianceAuth';

    /**
     *
     * @return string
     *
     * @since version 1.0.0
     */
    protected function getInput()
    {
        $buttonText = Text::_('VMPAYMENT_ALLIANCE_AUTH_BTN');
        $id = $this->id;

        $html = [];
        $html[] = '<div class="input-append">';
        $html[] = '    <button type="button" id="' . $id . '_btn" class="btn btn-primary">';
        $html[] = '        <span class="icon-loop" aria-hidden="true"></span> ' . $buttonText;
        $html[] = '    </button>';
        $html[] = '    <div id="' . $id . '_status" style="margin-top: 8px; font-weight: bold;"></div>';
        $html[] = '</div>';

        $this->addJs($id);

        return implode('', $html);
    }

    /**
     * @param $id
     *
     * @throws Exception
     * @since version 1.0.0
     */
    protected function addJs($id)
    {
        $doc = Factory::getApplication()->getDocument();
        $doc->addScriptDeclaration("
            document.addEventListener('DOMContentLoaded', function() {
                const btn = document.getElementById('{$id}_btn');
                const status = document.getElementById('{$id}_status');
                const methodId = document.querySelector('input[name=\"virtuemart_paymentmethod_id\"]').value;
                
                if (btn) {
                    btn.addEventListener('click', function() {
                        btn.disabled = true;
                        status.innerText = 'Connecting...';
                        fetch('index.php?option=com_virtuemart&view=plugin&vmtype=vmpayment&name=alliancepay&action=authorization&pm=alliancepay&format=json')
                        .then(response => response.json())
                        .then(data => {
                            data = JSON.parse(data);
                            if (data.success) {
                                status.innerHTML = '<span style=\"color:green;\">Success!</span>';
                            } else {
                                status.innerHTML = '<span style=\"color:red;\">Error: ' + data.message + '</span>';
                            }
                            btn.disabled = false;
                        });
                    });
                }
            });
        ");
    }
}