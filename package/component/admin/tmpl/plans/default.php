<?php
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var \Alliance\Component\Alliancepay\Administrator\View\Plans\HtmlView $this */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$pages = (int) ceil($this->total / $this::PAGE_SIZE);
$status = ['ACTIVE' => 'success', 'PAUSED' => 'warning', 'CANCELED' => 'secondary', 'ERROR' => 'danger'];
?>
<?php include __DIR__ . '/../_partials/nav.php'; ?>

<div class="mb-3">
    <a class="btn btn-primary" href="index.php?option=com_alliancepay&amp;view=plan&amp;layout=create"><?php echo Text::_('COM_ALLIANCEPAY_NEW_PLAN'); ?></a>
</div>

<form method="get" action="index.php" class="row g-2 mb-3">
    <input type="hidden" name="option" value="com_alliancepay">
    <input type="hidden" name="view" value="plans">
    <div class="col-auto"><input type="number" class="form-control" name="filter[product_id]" placeholder="<?php echo Text::_('COM_ALLIANCEPAY_FIELD_PRODUCTID'); ?>" value="<?php echo $this->filters['product_id'] ?: ''; ?>"></div>
    <div class="col-auto">
        <select class="form-select" name="filter[status]">
            <option value=""><?php echo Text::_('COM_ALLIANCEPAY_ALL_STATUSES'); ?></option>
            <?php foreach (array_keys($status) as $s) : ?>
                <option value="<?php echo $s; ?>"<?php echo $this->filters['status'] === $s ? ' selected' : ''; ?>><?php echo $s; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto"><button class="btn btn-secondary" type="submit"><?php echo Text::_('JSEARCH_FILTER'); ?></button></div>
</form>

<?php if (!$this->items) : ?>
    <div class="alert alert-info"><?php echo Text::_('COM_ALLIANCEPAY_NO_PLANS'); ?></div>
<?php else : ?>
    <table class="table table-striped">
        <thead><tr>
            <th>#</th><th><?php echo Text::_('COM_ALLIANCEPAY_FIELD_NAME'); ?></th>
            <th><?php echo Text::_('COM_ALLIANCEPAY_FIELD_PRODUCTID'); ?></th>
            <th><?php echo Text::_('COM_ALLIANCEPAY_COL_STATUS'); ?></th>
            <th><?php echo Text::_('COM_ALLIANCEPAY_COL_OFFERED'); ?></th>
            <th><?php echo Text::_('COM_ALLIANCEPAY_COL_ACTIVE_SUBS'); ?></th>
            <th><?php echo Text::_('COM_ALLIANCEPAY_COL_PROVIDER_PLAN'); ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($this->items as $plan) : ?>
            <tr>
                <td><?php echo (int) $plan->id; ?></td>
                <td><a href="index.php?option=com_alliancepay&amp;view=plan&amp;id=<?php echo (int) $plan->id; ?>"><?php echo $e($plan->name); ?></a></td>
                <td><?php echo (int) $plan->productId; ?></td>
                <td>
                    <span class="badge bg-<?php echo $status[$plan->status->value] ?? 'secondary'; ?>"><?php echo $e($plan->status->value); ?></span>
                    <?php if ($plan->syncStatus->value !== 'SYNCED') : ?><span class="badge bg-danger"><?php echo Text::_('COM_ALLIANCEPAY_NOT_SYNCED'); ?></span><?php endif; ?>
                    <?php if ($plan->archiveCandidate) : ?><span class="badge bg-warning text-dark"><?php echo Text::_('COM_ALLIANCEPAY_ARCHIVE_CANDIDATE'); ?></span><?php endif; ?>
                </td>
                <td><?php echo $plan->isActive ? Text::_('JYES') : Text::_('JNO'); ?></td>
                <td><a href="index.php?option=com_alliancepay&amp;view=subscriptions&amp;filter[plan_id]=<?php echo (int) $plan->id; ?>"><?php echo (int) $plan->activeSubscriptionsCount; ?></a></td>
                <td><code><?php echo $e($plan->providerPlanId); ?></code></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($pages > 1) : ?>
        <nav><ul class="pagination">
            <?php for ($p = 1; $p <= $pages; $p++) : ?>
                <li class="page-item<?php echo $p === $this->page ? ' active' : ''; ?>"><a class="page-link" href="index.php?option=com_alliancepay&amp;view=plans&amp;page=<?php echo $p; ?>"><?php echo $p; ?></a></li>
            <?php endfor; ?>
        </ul></nav>
    <?php endif; ?>
<?php endif; ?>
