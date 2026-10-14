<?php
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var \Alliance\Component\Alliancepay\Administrator\View\Subscriptions\HtmlView $this */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$pages = (int) ceil($this->total / $this::PAGE_SIZE);
$badge = ['ACTIVE' => 'success', 'PENDING' => 'info', 'FAILED' => 'danger', 'CANCELED' => 'secondary', 'COMPLETED' => 'dark'];
?>
<?php include __DIR__ . '/../_partials/nav.php'; ?>

<form method="get" action="index.php" class="row g-2 mb-3">
    <input type="hidden" name="option" value="com_alliancepay">
    <input type="hidden" name="view" value="subscriptions">
    <div class="col-auto"><input type="number" class="form-control" name="filter[plan_id]" placeholder="<?php echo Text::_('COM_ALLIANCEPAY_FIELD_PLANID'); ?>" value="<?php echo $this->filters['plan_id'] ?: ''; ?>"></div>
    <div class="col-auto"><input type="number" class="form-control" name="filter[user_id]" placeholder="<?php echo Text::_('COM_ALLIANCEPAY_FIELD_USERID'); ?>" value="<?php echo $this->filters['user_id'] ?: ''; ?>"></div>
    <div class="col-auto">
        <select class="form-select" name="filter[status]">
            <option value=""><?php echo Text::_('COM_ALLIANCEPAY_ALL_STATUSES'); ?></option>
            <?php foreach (array_keys($badge) as $s) : ?>
                <option value="<?php echo $s; ?>"<?php echo $this->filters['status'] === $s ? ' selected' : ''; ?>><?php echo $s; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto"><button class="btn btn-secondary" type="submit"><?php echo Text::_('JSEARCH_FILTER'); ?></button></div>
</form>

<?php if (!$this->items) : ?>
    <div class="alert alert-info"><?php echo Text::_('COM_ALLIANCEPAY_NO_SUBSCRIPTIONS'); ?></div>
<?php else : ?>
    <table class="table table-striped">
        <thead><tr><th>#</th><th><?php echo Text::_('COM_ALLIANCEPAY_FIELD_PLANID'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_FIELD_USERID'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_COL_VM_ORDER'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_COL_STATUS'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_COL_CREATED'); ?></th></tr></thead>
        <tbody>
        <?php foreach ($this->items as $s) : ?>
            <tr>
                <td><a href="index.php?option=com_alliancepay&amp;view=subscription&amp;id=<?php echo (int) $s->id; ?>"><?php echo (int) $s->id; ?></a></td>
                <td><a href="index.php?option=com_alliancepay&amp;view=plan&amp;id=<?php echo (int) $s->planId; ?>"><?php echo (int) $s->planId; ?></a></td>
                <td><?php echo (int) $s->virtuemartUserId; ?></td>
                <td><a href="index.php?option=com_virtuemart&amp;view=orders&amp;task=edit&amp;virtuemart_order_id=<?php echo (int) $s->virtuemartOrderId; ?>"><?php echo (int) $s->virtuemartOrderId; ?></a></td>
                <td><span class="badge bg-<?php echo $badge[$s->status->value] ?? 'secondary'; ?>"><?php echo $e($s->status->value); ?></span></td>
                <td><?php echo $e($s->createdAt); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($pages > 1) : ?>
        <nav><ul class="pagination">
            <?php for ($p = 1; $p <= $pages; $p++) : ?>
                <li class="page-item<?php echo $p === $this->page ? ' active' : ''; ?>"><a class="page-link" href="index.php?option=com_alliancepay&amp;view=subscriptions&amp;page=<?php echo $p; ?>"><?php echo $p; ?></a></li>
            <?php endfor; ?>
        </ul></nav>
    <?php endif; ?>
<?php endif; ?>
