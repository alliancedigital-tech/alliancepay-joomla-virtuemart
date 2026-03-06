<?php
defined ('_JEXEC') or die();
?>
<span class="vmpayment">
<?php if (!empty($viewData['logo'])): ?>
    <span class="vmCartAlliancePayLogo" >
        <img align="middle" src="<?= $viewData['logo'] ?>" /></span>
    </span>
<?php endif; ?>
    <span class="vmpayment_name"><?= $viewData['payment_name'] ?></span>
<?php if (!empty($viewData['payment_description'])): ?>
        <span class="vmpayment_description"><?php echo $viewData['payment_description'] ?> </span>
<?php endif; ?>
</span>