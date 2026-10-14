<?php
// Заборона прямого доступу
defined('_JEXEC') or die('Restricted access');
?>

<div class="alliance-transactions-history" style="margin-top: 20px;">
    <h4 style="color: #007bff; border-bottom: 2px solid #007bff; padding-bottom: 5px;">
        <?= JText::_('VMPAYMENT_ALLIANCE_TRANSACTIONS_HISTORY'); ?>
    </h4>

    <?php if (!empty($viewData['subscriptionStatus'])) : ?>
        <p style="margin: 4px 0 10px;">
            <strong><?= JText::_('VMPAYMENT_ALLIANCEPAY_SUB_STATUS'); ?>:</strong>
            <?= htmlspecialchars(JText::_('VMPAYMENT_ALLIANCEPAY_SUB_STATUS_' . $viewData['subscriptionStatus'])); ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($viewData['hppOrderId'])) : ?>
        <p style="margin: 4px 0 10px; color: #555; font-size: 0.9em;">
            HPP Order ID: <code><?= htmlspecialchars($viewData['hppOrderId']); ?></code>
        </p>
    <?php endif; ?>

    <?php if (!empty($viewData['transactions'])) : ?>
        <?php foreach ($viewData['transactions'] as $tx) : ?>
            <div class="alliance-tx-item" style="margin-bottom: 15px; border: 1px solid #ddd;">
                <table class="table table-bordered" style="width: 100%; margin-bottom: 0;">
                    <thead style="color: #007bff; padding: 5px;">
                    <tr>
                        <td><?= JText::_('VMPAYMENT_ALLIANCE_CB_TYPE'); ?>: <?= htmlspecialchars($tx['type']); ?></td>
                        <td>ID: <?= htmlspecialchars($tx['operationId']); ?></td>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td width="30%"><strong><?= JText::_('VMPAYMENT_ALLIANCE_CB_AMOUNT'); ?></strong></td>
                        <td>
                            <strong style="color: <?= (strpos($tx['type'], 'REFUND') !== false) ? 'red' : 'green'; ?>">
                                <?= number_format($tx['priceUAH'], 2, '.', ' '); ?> UAH
                                <?php if ($tx['priceUSD'] !== null) : ?>
                                    <br>
                                    <span style="color: #555; font-weight: normal; font-size: 0.95em;">
                                        &#8776; <?= number_format($tx['priceUSD'], 2, '.', ' '); ?> USD
                                    </span>
                                <?php elseif ($tx['priceEUR'] !== null) : ?>
                                    <br>
                                    <span style="color: #555; font-weight: normal; font-size: 0.95em;">
                                        &#8776; <?= number_format($tx['priceEUR'], 2, '.', ' '); ?> EUR
                                    </span>
                                <?php endif; ?>
                            </strong>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?= JText::_('VMPAYMENT_ALLIANCE_CB_STATUS'); ?></strong></td>
                        <td>
                            <span class="badge" style="background: <?= (strpos($tx['status'], 'SUCCESS') !== false ? '#17a2b8' : (in_array($tx['status'], ['WAITING', 'PROCESSING', 'RETRY', 'PENDING'], true) ? '#6c757d' : 'red')); ?>; color: #fff; padding: 3px 7px;">
                                <?= htmlspecialchars($tx['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?= JText::_('VMPAYMENT_ALLIANCE_CB_DATE'); ?></strong></td>
                        <td><?= htmlspecialchars($tx['creationDateTime']); ?></td>
                    </tr>
                    <?php if (!empty($tx['note'])) : ?>
                        <tr>
                            <td><strong><?= JText::_('VMPAYMENT_ALLIANCE_CB_NOTE'); ?></strong></td>
                            <td><?= htmlspecialchars($tx['note']); ?></td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <p><?= JText::_('VMPAYMENT_ALLIANCE_NO_TRANSACTIONS'); ?></p>
    <?php endif; ?>
</div>