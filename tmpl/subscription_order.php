<?php
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var array $viewData */
/** @var \Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\Subscription $subscription */
$subscription = $viewData['subscription'];
$canCancel = !empty($viewData['canCancel']);
$cancelUrl = (string) $viewData['cancelUrl'];
$token = (string) $viewData['token'];
?>
<div class="alliancepay-subscription" style="margin-top:8px">
    <div>
        <strong><?php echo Text::_('VMPAYMENT_ALLIANCEPAY_SUB_STATUS'); ?>:</strong>
        <?php echo htmlspecialchars(Text::_('VMPAYMENT_ALLIANCEPAY_SUB_STATUS_' . $subscription->status->value), ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <?php if ($canCancel) : ?>
        <form method="post" action="<?php echo htmlspecialchars($cancelUrl, ENT_QUOTES, 'UTF-8'); ?>"
              onsubmit="return confirm('<?php echo htmlspecialchars(Text::_('VMPAYMENT_ALLIANCEPAY_SUB_CANCEL_CONFIRM'), ENT_QUOTES, 'UTF-8'); ?>');">
            <input type="hidden" name="subscription_id" value="<?php echo (int) $subscription->id; ?>">
            <input type="hidden" name="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>" value="1">
            <button type="submit" class="button btn btn-secondary">
                <?php echo Text::_('VMPAYMENT_ALLIANCEPAY_SUB_CANCEL'); ?>
            </button>
        </form>
    <?php endif; ?>
</div>
