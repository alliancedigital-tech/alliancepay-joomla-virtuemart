<?php
defined ('_JEXEC') or die();

?>
<div class="post_payment_payment_name" style="width: 100%">
	<span class="post_payment_payment_name_title"><?= vmText::_ ('ALLIANCE_PAYMENT_INFO'); ?> </span>
	<?php echo  $viewData["payment_name"]; ?>
</div>

<div class="post_payment_order_number" style="width: 100%">
	<span class="post_payment_order_number_title"><?= vmText::_ ('ALLIANCE_ORDER_NUMBER'); ?> </span>
	<?php echo  $viewData["order_number"]; ?>
</div>

<div class="post_payment_order_total" style="width: 100%">
	<span class="post_payment_order_total_title"><?= vmText::_ ('ALLIANCE_ORDER_PRINT_TOTAL'); ?> </span>
	<?=  $viewData['display_total_in_payment_currency']; ?>
</div>
<?php if ($viewData["order_link"]): ?>
<a class="vm-button-correct" href="<?= JRoute::_($viewData["order_link"], false)?>">
    <?= vmText::_('ALLIANCE_ORDER_VIEW_ORDER'); ?>
</a>
<?php endif; ?>






