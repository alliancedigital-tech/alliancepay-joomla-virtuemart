<?php
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var \Alliance\Component\Alliancepay\Administrator\View\Plan\HtmlView $this */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$plan = $this->plan;
$token = HTMLHelper::_('form.token');
$eligible = $plan->status->value === 'ACTIVE' && $plan->syncStatus->value === 'SYNCED';
$earliest = gmdate('Y-m-d\TH:i', time() + $this->repriceMinDays * 86400);
?>
<?php include __DIR__ . '/../_partials/nav.php'; ?>

<?php if ($plan->syncStatus->value !== 'SYNCED') : ?>
    <div class="alert alert-danger">
        <strong><?php echo Text::_('COM_ALLIANCEPAY_PLAN_NOT_READY'); ?></strong>
        <?php echo $e($plan->syncError); ?>
        <form method="post" action="index.php?option=com_alliancepay&amp;task=plan.retrySync" class="mt-2">
            <input type="hidden" name="id" value="<?php echo (int) $plan->id; ?>">
            <?php echo $token; ?>
            <button class="btn btn-warning" type="submit"><?php echo Text::_('COM_ALLIANCEPAY_RETRY_SYNC'); ?></button>
        </form>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-6">
        <table class="table table-sm">
            <tr><th><?php echo Text::_('COM_ALLIANCEPAY_FIELD_NAME'); ?></th><td><?php echo $e($plan->name); ?></td></tr>
            <tr><th><?php echo Text::_('COM_ALLIANCEPAY_FIELD_PRODUCTID'); ?></th><td><?php echo (int) $plan->productId; ?></td></tr>
            <tr><th><?php echo Text::_('COM_ALLIANCEPAY_COL_PROVIDER_PLAN'); ?></th><td><code><?php echo $e($plan->providerPlanId); ?></code></td></tr>
            <tr><th><?php echo Text::_('COM_ALLIANCEPAY_COL_VERSION'); ?></th><td><code><?php echo $e($plan->versionId); ?></code></td></tr>
            <tr><th><?php echo Text::_('COM_ALLIANCEPAY_COL_STATUS'); ?></th><td><?php echo $e($plan->status->value); ?> / <?php echo $e($plan->syncStatus->value); ?></td></tr>
            <tr><th><?php echo Text::_('COM_ALLIANCEPAY_COL_OFFERED'); ?></th><td><?php echo $plan->isActive ? Text::_('JYES') : Text::_('JNO'); ?></td></tr>
            <tr><th><?php echo Text::_('COM_ALLIANCEPAY_COL_ACTIVE_SUBS'); ?></th><td><?php echo (int) $plan->activeSubscriptionsCount; ?></td></tr>
            <?php if ($plan->appliesFrom) : ?><tr><th><?php echo Text::_('COM_ALLIANCEPAY_FIELD_APPLIESFROM'); ?></th><td><?php echo $e($plan->appliesFrom); ?> UTC</td></tr><?php endif; ?>
        </table>

        <form method="post" action="index.php?option=com_alliancepay&amp;task=plan.setOffered" class="d-inline">
            <input type="hidden" name="id" value="<?php echo (int) $plan->id; ?>">
            <input type="hidden" name="offered" value="<?php echo $plan->isActive ? 0 : 1; ?>">
            <?php echo $token; ?>
            <button class="btn btn-secondary" type="submit"
                <?php if ($plan->isActive) : ?>onclick="return confirm('<?php echo $e(Text::_('COM_ALLIANCEPAY_CONFIRM_STOP_OFFERING')); ?>');"<?php endif; ?>>
                <?php echo Text::_($plan->isActive ? 'COM_ALLIANCEPAY_STOP_OFFERING' : 'COM_ALLIANCEPAY_RESUME_OFFERING'); ?>
            </button>
        </form>
        <?php if ($plan->isActive === false) : ?>
            <p class="small text-muted mt-2"><?php echo Text::_('COM_ALLIANCEPAY_STOP_OFFERING_HELP'); ?></p>
        <?php endif; ?>

        <?php if ($plan->archiveCandidate && $this->canArchive) : ?>
            <div class="alert alert-warning mt-3">
                <?php echo Text::_('COM_ALLIANCEPAY_ARCHIVE_CANDIDATE_HELP'); ?>
                <form method="post" action="index.php?option=com_alliancepay&amp;task=plan.confirmArchive" class="mt-2">
                    <input type="hidden" name="id" value="<?php echo (int) $plan->id; ?>">
                    <?php echo $token; ?>
                    <button class="btn btn-danger" type="submit" onclick="return confirm('<?php echo $e(Text::_('COM_ALLIANCEPAY_CONFIRM_ARCHIVE')); ?>');"><?php echo Text::_('COM_ALLIANCEPAY_CONFIRM_ARCHIVE_BTN'); ?></button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-6">
        <h3><?php echo Text::_('COM_ALLIANCEPAY_SCHEDULE'); ?></h3>
        <table class="table table-sm">
            <thead><tr><th><?php echo Text::_('COM_ALLIANCEPAY_COL_PAYMENTS'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_FIELD_AMOUNT'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_COL_EVERY'); ?></th></tr></thead>
            <tbody>
            <?php foreach ($this->formatter->describe($plan->rules) as $row) : ?>
                <tr>
                    <td><?php echo (int) $row['from']; ?>–<?php echo $row['to'] >= 999 ? '∞' : (int) $row['to']; ?></td>
                    <td><?php echo $row['isTrial'] ? Text::_('COM_ALLIANCEPAY_STAGE_TRIAL') : number_format($row['amount'], 2, '.', ' ') . ' ' . $e($row['currency']); ?></td>
                    <td><?php echo (int) $row['intervalValue']; ?> <?php echo Text::_('COM_ALLIANCEPAY_UNIT_' . $row['intervalUnit']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($this->canReprice && $eligible) : ?>
    <hr>
    <h3><?php echo Text::_('COM_ALLIANCEPAY_REPRICE'); ?></h3>
    <div class="alert alert-warning">
        <?php echo Text::sprintf('COM_ALLIANCEPAY_REPRICE_WARNING', (int) $plan->activeSubscriptionsCount, (int) $this->repriceMinDays); ?>
    </div>
    <form method="post" action="index.php?option=com_alliancepay&amp;task=plan.reprice"
          onsubmit="return confirm('<?php echo $e(Text::sprintf('COM_ALLIANCEPAY_REPRICE_CONFIRM', (int) $plan->activeSubscriptionsCount)); ?>');">
        <input type="hidden" name="id" value="<?php echo (int) $plan->id; ?>">
        <?php echo $token; ?>
        <?php $editorRows = $this->formRows; $editorMode = 'plan'; include __DIR__ . '/../_partials/rules_editor.php'; ?>
        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <label class="form-label" for="applies_from"><?php echo Text::_('COM_ALLIANCEPAY_FIELD_APPLIESFROM'); ?></label>
                <input type="datetime-local" class="form-control" id="applies_from" name="applies_from" min="<?php echo $earliest; ?>" required>
                <div class="form-text"><?php echo Text::sprintf('COM_ALLIANCEPAY_APPLIES_FROM_HELP', (int) $this->repriceMinDays); ?></div>
            </div>
            <div class="col-md-8">
                <label class="form-label" for="reason"><?php echo Text::_('COM_ALLIANCEPAY_FIELD_REASON'); ?></label>
                <input type="text" class="form-control" id="reason" name="reason" required>
            </div>
        </div>
        <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" id="customers_notified" name="customers_notified" value="1" required>
            <label class="form-check-label" for="customers_notified"><?php echo Text::_('COM_ALLIANCEPAY_CUSTOMERS_NOTIFIED'); ?></label>
        </div>
        <button class="btn btn-danger mt-3" type="submit"><?php echo Text::_('COM_ALLIANCEPAY_REPRICE_BTN'); ?></button>
    </form>
<?php endif; ?>

<?php if ($this->history) : ?>
    <hr>
    <h3><?php echo Text::_('COM_ALLIANCEPAY_VERSION_HISTORY'); ?></h3>
    <table class="table table-sm">
        <thead><tr><th><?php echo Text::_('COM_ALLIANCEPAY_COL_WHEN'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_COL_VERSION'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_FIELD_APPLIESFROM'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_COL_CHANGE'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_FIELD_REASON'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_COL_BY'); ?></th></tr></thead>
        <tbody>
        <?php foreach ($this->history as $h) : ?>
            <tr>
                <td><?php echo $e($h['created_at']); ?></td>
                <td><code><?php echo $e($h['old_version_id']); ?></code> → <code><?php echo $e($h['new_version_id']); ?></code></td>
                <td><?php echo $e($h['applies_from']); ?></td>
                <td>
                    <?php foreach ($this->formatter->describe($h['old_rules']) as $r) : echo $e(number_format($r['amount'], 2, '.', '') . ' ' . $r['currency']) . ' '; endforeach; ?>
                    →
                    <?php foreach ($this->formatter->describe($h['new_rules']) as $r) : echo $e(number_format($r['amount'], 2, '.', '') . ' ' . $r['currency']) . ' '; endforeach; ?>
                </td>
                <td><?php echo $e($h['reason']); ?></td>
                <td><?php echo (int) $h['created_by']; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p><a href="index.php?option=com_alliancepay&amp;view=audit&amp;plan_id=<?php echo (int) $plan->id; ?>"><?php echo Text::_('COM_ALLIANCEPAY_MENU_AUDIT'); ?></a></p>
