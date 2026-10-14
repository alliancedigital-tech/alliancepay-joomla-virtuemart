<?php
defined('_JEXEC') or die('Restricted access');
/**
 * Just the "Check order status" ("Оновити") button, extracted from refund_form.php for orders that
 * must not show the partial/full refund UI (a subscription order — refunds there are a different,
 * not-yet-implemented flow; RefundProcessor/AllianceOrderTable know nothing about subscriptions).
 */
?>

<div class="alliance-refund-wrapper" style="margin-top: 20px; border: 1px solid #ccc; padding: 15px;">
    <h4><?= JText::_('VMPAYMENT_ALLIANCE_CHECK_STATUS_TITLE'); ?></h4>

    <form id="alliance-refund-form">
        <div class="refund-controls" style="margin-top: 10px;">
            <button type="button" class="btn btn-warning" style="margin: 10px;" onclick="checkAllianceOrderStatus(<?= $viewData['order_id']; ?>)">
                <?= JText::_('VMPAYMENT_ALLIANCE_CHECK_ORDER_STATUS'); ?>
            </button>
        </div>
    </form>
</div>

<script type="text/javascript">
    function checkAllianceOrderStatus(orderId) {
        let url = 'index.php?option=com_virtuemart&view=plugin&vmtype=vmpayment&name=alliancepay&task=checkOrderStatus&pm=alliancepay' +
            '&onme=' + orderId;

        fetch(url).then(response => response.json())
            .then(data => {
                data = JSON.parse(data);
                if (data.success) {
                    location.reload();
                } else {
                    alert('Помилка: ' + data.message);
                }
            });
    }
</script>
