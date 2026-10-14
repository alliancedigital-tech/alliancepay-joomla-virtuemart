<?php
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var \Alliance\Component\Alliancepay\Administrator\View\Subscription\HtmlView $this */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sub = $this->data['subscription'];
$plan = $this->data['plan'];
$token = HTMLHelper::_('form.token');
$cancellable = in_array($sub->status->value, ['ACTIVE', 'PENDING'], true) && $sub->providerOrderId !== null;
?>
<?php include __DIR__ . '/../_partials/nav.php'; ?>

<div class="row">
    <div class="col-lg-6">
        <table class="table table-sm">
            <tr><th><?php echo Text::_('COM_ALLIANCEPAY_COL_STATUS'); ?></th><td><?php echo $e($sub->status->value); ?></td></tr>
            <tr><th><?php echo Text::_('COM_ALLIANCEPAY_FIELD_PLANID'); ?></th><td><?php if ($plan) : ?><a href="index.php?option=com_alliancepay&amp;view=plan&amp;id=<?php echo (int) $plan->id; ?>"><?php echo $e($plan->name); ?></a><?php endif; ?></td></tr>
            <tr><th><?php echo Text::_('COM_ALLIANCEPAY_FIELD_USERID'); ?></th><td><?php echo (int) $sub->virtuemartUserId; ?></td></tr>
            <tr><th><?php echo Text::_('COM_ALLIANCEPAY_COL_VM_ORDER'); ?></th><td><a href="index.php?option=com_virtuemart&amp;view=orders&amp;task=edit&amp;virtuemart_order_id=<?php echo (int) $sub->virtuemartOrderId; ?>"><?php echo (int) $sub->virtuemartOrderId; ?></a></td></tr>
            <tr><th><?php echo Text::_('COM_ALLIANCEPAY_COL_HPP_ORDER'); ?></th><td><code><?php echo $e($sub->providerOrderId); ?></code></td></tr>
            <tr><th><?php echo Text::_('COM_ALLIANCEPAY_COL_CREATED'); ?></th><td><?php echo $e($sub->createdAt); ?></td></tr>
        </table>
        <?php if ($cancellable) : ?>
            <form method="post" action="index.php?option=com_alliancepay&amp;task=subscription.cancel" onsubmit="return confirm('<?php echo $e(Text::_('COM_ALLIANCEPAY_CONFIRM_CANCEL_SUBSCRIPTION')); ?>');">
                <input type="hidden" name="id" value="<?php echo (int) $sub->id; ?>">
                <?php echo $token; ?>
                <button class="btn btn-danger" type="submit"><?php echo Text::_('COM_ALLIANCEPAY_CANCEL_SUBSCRIPTION'); ?></button>
            </form>
        <?php endif; ?>
    </div>
    <div class="col-lg-6">
        <h3><?php echo Text::_('COM_ALLIANCEPAY_RULES_SNAPSHOT'); ?></h3>
        <table class="table table-sm">
            <thead><tr><th><?php echo Text::_('COM_ALLIANCEPAY_COL_PAYMENTS'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_FIELD_AMOUNT'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_COL_EVERY'); ?></th></tr></thead>
            <tbody>
            <?php foreach ($sub->rulesSnapshot as $r) : ?>
                <tr>
                    <td><?php echo (int) ($r['paymentNumberFrom'] ?? 0); ?>–<?php echo (int) ($r['paymentNumberTo'] ?? 0) >= 999 ? '∞' : (int) ($r['paymentNumberTo'] ?? 0); ?></td>
                    <td><?php echo $e(number_format(((int) ($r['coinAmount'] ?? 0)) / 100, 2, '.', ' ')); ?></td>
                    <td><?php echo (int) ($r['intervalValue'] ?? 0); ?> <?php echo $e($r['intervalUnit'] ?? ''); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<h3 class="mt-3"><?php echo Text::_('COM_ALLIANCEPAY_SCHEDULED_PAYMENTS'); ?></h3>
<?php if (!$this->data['payments']) : ?>
    <div class="alert alert-info"><?php echo Text::_('COM_ALLIANCEPAY_NO_PAYMENTS'); ?></div>
<?php else : ?>
    <table class="table table-sm table-striped">
        <thead><tr><th>#</th><th><?php echo Text::_('COM_ALLIANCEPAY_COL_PAYMENT_NO'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_COL_STATUS'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_COL_SCHEDULED'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_COL_EXECUTED'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_FIELD_AMOUNT'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_COL_OPERATION'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_COL_RETRIES'); ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($this->data['payments'] as $p) : ?>
            <tr<?php echo $p['needs_review'] ? ' class="table-warning"' : ''; ?>>
                <td><?php echo (int) $p['id']; ?></td>
                <td><?php echo $p['payment_number'] !== null ? (int) $p['payment_number'] : '—'; ?></td>
                <td><?php echo $e($p['status']); ?></td>
                <td><?php echo $e($p['scheduled_date']); ?></td>
                <td><?php echo $e($p['executed_at']); ?></td>
                <td>
                    <?php echo $p['coin_amount'] !== null ? $e(number_format($p['coin_amount'] / 100, 2, '.', ' ')) : '—'; ?>
                    <?php if ($p['needs_review']) : ?><br><small class="text-danger"><?php echo $e($p['review_note']); ?></small><?php endif; ?>
                </td>
                <td><code><?php echo $e($p['operation_id']); ?></code></td>
                <td><?php echo (int) $p['retry_count']; ?></td>
                <td>
                    <?php if ($this->canRunNow && !empty($p['scheduled_payment_id']) && !in_array($p['status'], ['SUCCESS', 'INVALIDATED'], true)) : ?>
                        <form method="post" action="index.php?option=com_alliancepay&amp;task=subscription.runNow" onsubmit="return confirm('<?php echo $e(Text::_('COM_ALLIANCEPAY_CONFIRM_RUNNOW')); ?>');">
                            <input type="hidden" name="id" value="<?php echo (int) $sub->id; ?>">
                            <input type="hidden" name="scheduled_payment_row" value="<?php echo (int) $p['id']; ?>">
                            <?php echo $token; ?>
                            <button class="btn btn-sm btn-warning" type="submit"><?php echo Text::_('COM_ALLIANCEPAY_RUN_NOW'); ?></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h3 class="mt-3"><?php echo Text::_('COM_ALLIANCEPAY_PROVIDER_OPERATIONS'); ?></h3>
<p class="text-muted small"><?php echo Text::_('COM_ALLIANCEPAY_OPERATIONS_LIVE'); ?></p>
<?php if ($this->data['warning'] !== null) : ?>
    <div class="alert alert-warning"><?php echo Text::sprintf('COM_ALLIANCEPAY_OPERATIONS_UNAVAILABLE', $e($this->data['warning'])); ?></div>
<?php elseif (!$this->data['operations']) : ?>
    <div class="alert alert-info"><?php echo Text::_('COM_ALLIANCEPAY_NO_OPERATIONS'); ?></div>
<?php else : ?>
    <table class="table table-sm table-striped">
        <thead><tr><th><?php echo Text::_('COM_ALLIANCEPAY_COL_OPERATION'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_COL_TYPE'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_COL_STATUS'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_FIELD_AMOUNT'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_COL_CREATED'); ?></th></tr></thead>
        <tbody>
        <?php foreach ($this->data['operations'] as $op) : ?>
            <tr>
                <td><code><?php echo $e($op['operationId'] ?? ''); ?></code></td>
                <td><?php echo $e($op['type'] ?? ''); ?></td>
                <td><?php echo $e($op['status'] ?? ''); ?></td>
                <td><?php echo isset($op['coinAmount']) ? $e(number_format(((int) $op['coinAmount']) / 100, 2, '.', ' ')) : '—'; ?></td>
                <td><?php echo $e($op['creationDateTime'] ?? ''); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
