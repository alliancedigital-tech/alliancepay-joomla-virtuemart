<?php
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var \Alliance\Component\Alliancepay\Administrator\View\Audit\HtmlView $this */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<?php include __DIR__ . '/../_partials/nav.php'; ?>

<?php if (!$this->items) : ?>
    <div class="alert alert-info"><?php echo Text::_('COM_ALLIANCEPAY_NO_AUDIT'); ?></div>
<?php else : ?>
    <table class="table table-sm table-striped">
        <thead><tr><th><?php echo Text::_('COM_ALLIANCEPAY_COL_WHEN'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_COL_ACTION'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_FIELD_PLANID'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_COL_BY'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_COL_DETAILS'); ?></th></tr></thead>
        <tbody>
        <?php foreach ($this->items as $row) : ?>
            <tr>
                <td><?php echo $e($row['created_at']); ?></td>
                <td><code><?php echo $e($row['action']); ?></code></td>
                <td><?php echo $row['plan_id'] ? '<a href="index.php?option=com_alliancepay&amp;view=plan&amp;id=' . (int) $row['plan_id'] . '">' . (int) $row['plan_id'] . '</a>' : ''; ?></td>
                <td><?php echo $row['user_id'] ? (int) $row['user_id'] : Text::_('COM_ALLIANCEPAY_SYSTEM'); ?></td>
                <td><small><?php echo $e(json_encode($row['details'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></small></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
