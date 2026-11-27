<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 *
 * "Fiscalization" block of the order page: every successful payment / refund of the order with its
 * fiscalization status and the buttons allowed for that status.
 */
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$orderId = (int) $viewData['order_id'];
$rows = $viewData['rows'];
$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$url = 'index.php?option=com_virtuemart&view=plugin&vmtype=vmpayment&name=alliancepay&pm=alliancepay&task=';
?>

<div class="alliance-fiscalization-wrapper" style="margin-top: 20px; border: 1px solid #ccc; padding: 15px;">
    <h4><?= Text::_('VMPAYMENT_ALLIANCEPAY_FISCAL_BLOCK_TITLE'); ?></h4>

    <?php if (!empty($viewData['error'])) : ?>
        <p class="alert alert-danger"><?= Text::_('VMPAYMENT_ALLIANCEPAY_FISCAL_BLOCK_LOAD_ERROR'); ?> <code><?= $e($viewData['error']); ?></code></p>
    <?php endif; ?>

    <?php if ($rows === [] && empty($viewData['error'])) : ?>
        <p><?= Text::_('VMPAYMENT_ALLIANCEPAY_FISCAL_BLOCK_EMPTY'); ?></p>
    <?php else : ?>
        <table class="adminlist table table-striped" width="100%">
            <thead>
            <tr>
                <th><?= Text::_('VMPAYMENT_ALLIANCEPAY_FISCAL_COL_OPERATION'); ?></th>
                <th><?= Text::_('VMPAYMENT_ALLIANCEPAY_FISCAL_COL_AMOUNT'); ?></th>
                <th><?= Text::_('VMPAYMENT_ALLIANCEPAY_FISCAL_COL_STATUS'); ?></th>
                <th><?= Text::_('VMPAYMENT_ALLIANCEPAY_FISCAL_COL_RECEIPT'); ?></th>
                <th><?= Text::_('VMPAYMENT_ALLIANCEPAY_FISCAL_COL_ERROR'); ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row) : ?>
                <tr>
                    <td>
                        <strong><?= Text::_('VMPAYMENT_ALLIANCEPAY_FISCAL_TYPE_' . $e($row['type'])); ?></strong><br>
                        <small><?= $e($row['operation_id']); ?></small>
                    </td>
                    <td><?= $row['amount'] !== null ? $e(number_format((float) $row['amount'], 2, '.', ' ')) . ' UAH' : '&mdash;'; ?></td>
                    <td><?= $row['status'] !== null ? Text::_('VMPAYMENT_ALLIANCEPAY_FISCAL_STATUS_' . $e($row['status'])) : Text::_('VMPAYMENT_ALLIANCEPAY_FISCAL_STATUS_NONE'); ?></td>
                    <td>
                        <?php if ($row['fiscal_number'] !== null) : ?>
                            <?= Text::_('VMPAYMENT_ALLIANCEPAY_FISCAL_COL_FISCAL_NUMBER'); ?>: <?= $e($row['fiscal_number']); ?><br>
                        <?php endif; ?>
                        <?php if ($row['fiscalization_date_time'] !== null) : ?>
                            <?= $e(HTMLHelper::_('date', $row['fiscalization_date_time'], Text::_('DATE_FORMAT_LC5'), true)); ?><br>
                        <?php endif; ?>
                        <?php if ($row['receipt_url'] !== null) : ?>
                            <a href="<?= $e($row['receipt_url']); ?>" target="_blank" rel="noopener"><?= Text::_('VMPAYMENT_ALLIANCEPAY_FISCAL_VIEW_RECEIPT'); ?></a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($row['error_code'])) : ?><strong><?= $e($row['error_code']); ?></strong> <?php endif; ?>
                        <?= $e($row['error_description']); ?>
                    </td>
                    <td>
                        <?php if ($row['can_fiscalize']) : ?>
                            <button type="button" class="btn btn-warning" onclick="allianceFiscalAction(this, 'fiscalize', <?= $e(json_encode($row['operation_id'])); ?>)">
                                <?= Text::_('VMPAYMENT_ALLIANCEPAY_FISCAL_BTN_FISCALIZE'); ?>
                            </button>
                        <?php endif; ?>
                        <?php if ($row['can_refresh']) : ?>
                            <button type="button" class="btn btn-secondary" onclick="allianceFiscalAction(this, 'fiscalizationStatus', <?= $e(json_encode($row['operation_id'])); ?>)">
                                <?= Text::_('VMPAYMENT_ALLIANCEPAY_FISCAL_BTN_REFRESH'); ?>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script type="text/javascript">
    function allianceFiscalAction(button, task, operationId) {
        const body = new FormData();
        body.append(<?= json_encode((string) $viewData['token']); ?>, '1');
        body.append('onme', <?= $orderId; ?>);
        body.append('operation_id', operationId);
        button.setAttribute('disabled', 'disabled');

        fetch(<?= json_encode($url); ?> + task, {method: 'POST', body: body, credentials: 'same-origin'})
            .then(response => response.json())
            .then(data => {
                if (typeof data === 'string') {
                    data = JSON.parse(data);
                }
                if (!data.success && data.message) {
                    alert(data.message);
                }
                location.reload();
            })
            .catch(() => {
                button.removeAttribute('disabled');
                alert(<?= json_encode(Text::_('VMPAYMENT_ALLIANCEPAY_FISCAL_ERR_API_UNAVAILABLE')); ?>.replace('%s', 'network'));
            });
    }
</script>
