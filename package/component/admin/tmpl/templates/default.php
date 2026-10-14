<?php
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var \Alliance\Component\Alliancepay\Administrator\View\Templates\HtmlView $this */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<?php include __DIR__ . '/../_partials/nav.php'; ?>

<div class="mb-3"><a class="btn btn-primary" href="index.php?option=com_alliancepay&amp;view=template"><?php echo Text::_('COM_ALLIANCEPAY_TEMPLATE_NEW'); ?></a></div>

<?php if (!$this->items) : ?>
    <div class="alert alert-info"><?php echo Text::_('COM_ALLIANCEPAY_NO_TEMPLATES'); ?></div>
<?php else : ?>
    <table class="table table-striped">
        <thead><tr><th><?php echo Text::_('COM_ALLIANCEPAY_FIELD_CODE'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_FIELD_NAME'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_COL_STATUS'); ?></th><th><?php echo Text::_('COM_ALLIANCEPAY_COL_USED'); ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($this->items as $t) : ?>
            <tr>
                <td><code><?php echo $e($t->code); ?></code></td>
                <td><a href="index.php?option=com_alliancepay&amp;view=template&amp;id=<?php echo (int) $t->id; ?>"><?php echo $e($t->name); ?></a></td>
                <td><?php echo $e($t->status->value); ?></td>
                <td><?php echo !empty($this->used[$t->id]) ? Text::_('JYES') : Text::_('JNO'); ?></td>
                <td>
                    <?php if ($t->status->value === 'active') : ?>
                        <form method="post" action="index.php?option=com_alliancepay&amp;task=template.archive" class="d-inline" onsubmit="return confirm('<?php echo $e(Text::_('COM_ALLIANCEPAY_CONFIRM_ARCHIVE_TEMPLATE')); ?>');">
                            <input type="hidden" name="id" value="<?php echo (int) $t->id; ?>">
                            <?php echo HTMLHelper::_('form.token'); ?>
                            <button class="btn btn-sm btn-outline-secondary" type="submit"><?php echo Text::_('COM_ALLIANCEPAY_ARCHIVE'); ?></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
