<?php
defined('_JEXEC') or die('Restricted access');
?>

<div class="alliance-refund-wrapper" style="margin-top: 20px; border: 1px solid #ccc; padding: 15px;">
    <h4><?= JText::_('VMPAYMENT_ALLIANCE_PARTIAL_REFUND_TITLE'); ?></h4>

    <form id="alliance-refund-form">
        <table class="adminlist table table-striped" width="100%">
            <thead>
            <tr>
                <th width="20"><input id="partial-refund-checkbox" type="checkbox" onclick="checkAllRefunds(this)" /></th>
                <th><?= JText::_('VMPAYMENT_ALLIANCE_PRODUCT_NAME'); ?></th>
                <th width="100"><?= JText::_('VMPAYMENT_ALLIANCE_QTY'); ?></th>
                <th width="100"><?= JText::_('VMPAYMENT_ALLIANCE_PRICE'); ?></th>
                <th width="100"><?= JText::_('VMPAYMENT_ALLIANCE_STATUS'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($viewData['items'] as $item) : ?>
                <tr>
                    <td>
                        <input type="checkbox" name="refund_items[]"
                               value="<?= $item->virtuemart_order_item_id; ?>"
                               data-price="<?= $item->product_final_price; ?>"
                               data-qty="<?= $item->product_quantity; ?>"
                                <?= $item->order_status === 'R' ? 'disabled="disabled"' : ''; ?>/>
                    </td>
                    <td><?= $item->order_item_name; ?> (SKU: <?= $item->order_item_sku; ?>)</td>
                    <td><?= (int)$item->product_quantity; ?></td>
                    <td><?= $item->product_final_price; ?></td>
                    <td><?= $this->getStatusName($item->order_status); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="refund-controls" style="margin-top: 10px;">
            <input type="number" id="refund_total_amount" name="amount" step="0.01" placeholder="Total amount to refund" readonly />
            <button id="partial-refund-btn" type="button" class="btn btn-warning" style="margin: 10px;" onclick="processAllianceRefund(<?= $viewData['order_id']; ?>)">
                <?= JText::_('VMPAYMENT_ALLIANCE_EXECUTE_REFUND'); ?>
            </button>
        </div>
    </form>
</div>
<div class="alliance-refund-wrapper" style="margin-top: 20px; border: 1px solid #ccc; padding: 15px;">
    <h4><?= JText::_('VMPAYMENT_ALLIANCE_REFUND_TITLE'); ?></h4>

    <form id="alliance-refund-form">
        <div class="refund-controls" style="margin-top: 10px;">
            <button type="button"
                    class="btn btn-warning"
                    style="margin: 10px;" onclick="processAllianceFullRefund(<?= $viewData['order_id']; ?>)"
                    <?= $viewData['isFullRefundAllowed'] ? '' : 'disabled="disabled"'; ?> >
                <?= JText::_('VMPAYMENT_ALLIANCE_EXECUTE_FULL_REFUND'); ?>
            </button>
        </div>
    </form>
</div>

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
    document.querySelectorAll('input[name="refund_items[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            let total = 0;
            document.querySelectorAll('input[name="refund_items[]"]:checked').forEach(checked => {
                total += parseFloat(checked.getAttribute('data-price')) * parseInt(checked.getAttribute('data-qty'));
            });
            document.getElementById('refund_total_amount').value = total.toFixed(2);
        });
    });

    const items = document.getElementsByName('refund_items[]');
    const allDisabled = [...items].every(item => item.disabled);

    if (allDisabled) {
        document.getElementById('partial-refund-btn').setAttribute('disabled', 'disabled');
        document.getElementById('partial-refund-checkbox').setAttribute('disabled', 'disabled');
    }

    function checkAllRefunds(source) {
        var checkboxes = document.getElementsByName('refund_items[]');
        for(var i in checkboxes) {
            if (!checkboxes[i].disabled) {
                checkboxes[i].checked = source.checked
            }
        };
        document.querySelector('input[name="refund_items[]"]').dispatchEvent(new Event('change'));
    }

    function processAllianceRefund(orderId) {
        let selectedItems = [];
        document.querySelectorAll('input[name="refund_items[]"]:checked').forEach(cb => {
            selectedItems.push(cb.value);
        });

        if (selectedItems.length === 0) {
            alert('Виберіть хоча б один товар для повернення');
            return;
        }

        let amount = document.getElementById('refund_total_amount').value;

        let url = 'index.php?option=com_virtuemart&view=plugin&vmtype=vmpayment&name=alliancepay&task=processPartialRefund&pm=alliancepay' +
            '&onme=' + orderId +
            '&amount=' + amount +
            '&item_ids=' + selectedItems.join(',');

        fetch(url).then(response => response.json())
            .then(data => {
                data = JSON.parse(data);
                if (data.success) {
                    alert('Повернення за вибрані товари виконано!');
                    location.reload();
                } else {
                    alert('Помилка: ' + data.message);
                }
            });
    }

    function processAllianceFullRefund(orderId) {
        let url = 'index.php?option=com_virtuemart&view=plugin&vmtype=vmpayment&name=alliancepay&task=processFullRefund&pm=alliancepay' +
            '&onme=' + orderId;

        fetch(url).then(response => response.json())
            .then(data => {
                data = JSON.parse(data);
                if (data.success) {
                    alert('Повернення за вибрані товари виконано!');
                    location.reload();
                } else {
                    alert('Помилка: ' + data.message);
                }
            });
    }

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
