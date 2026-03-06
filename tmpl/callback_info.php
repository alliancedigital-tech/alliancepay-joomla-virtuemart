<?php
// Заборона прямого доступу
defined('_JEXEC') or die('Restricted access');
?>

<div class="alliance-transactions-history" style="margin-top: 20px;">
    <h4 style="color: #007bff; border-bottom: 2px solid #007bff; padding-bottom: 5px;">
        <?= JText::_('VMPAYMENT_ALLIANCE_TRANSACTIONS_HISTORY'); ?>
    </h4>

    <?php if (!empty($viewData['transactions'])) : ?>
        <?php foreach ($viewData['transactions'] as $tx) : ?>
            <div class="alliance-tx-item" style="margin-bottom: 15px; border: 1px solid #ddd;">
                <table class="table table-bordered" style="width: 100%; margin-bottom: 0;">
                    <thead style="color: #007bff; padding: 5px;">
                    <tr>
                        <td><?= JText::_('VMPAYMENT_ALLIANCE_CB_TYPE'); ?>: <?= $tx['type']; ?></td>
                        <td>ID: <?= $tx['operationId']; ?></td>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td width="30%"><strong><?= JText::_('VMPAYMENT_ALLIANCE_CB_AMOUNT'); ?></strong></td>
                        <td>
                            <strong style="color: <?= (strpos($tx['type'], 'REFUND') !== false) ? 'red' : 'green'; ?>">
                                <?= number_format($tx['coinAmount'] / 100, 2, '.', ' '); ?>
                            </strong>
                            (<?= $tx['coinAmount']; ?>)
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?= JText::_('VMPAYMENT_ALLIANCE_CB_STATUS'); ?></strong></td>
                        <td>
                                <span class="badge" style="background: <?= (strpos($tx['status'], 'SUCCESS') !== false ? '#17a2b8' : 'red')?>; color: #fff; padding: 3px 7px;">
                                    <?= $tx['status']; ?>
                                </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?= JText::_('VMPAYMENT_ALLIANCE_CB_DATE'); ?></strong></td>
                        <td><?= $tx['creationDateTime']; ?></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <p><?= JText::_('VMPAYMENT_ALLIANCE_NO_TRANSACTIONS'); ?></p>
    <?php endif; ?>
</div>