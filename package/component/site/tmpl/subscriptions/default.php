<?php
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Alliance\Component\Alliancepay\Site\View\Subscriptions\HtmlView $this */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="alliancepay-my-subscriptions">
    <h1><?php echo Text::_('COM_ALLIANCEPAY_SITE_TITLE'); ?></h1>

    <?php if (!$this->rows) : ?>
        <p><?php echo Text::_('COM_ALLIANCEPAY_SITE_NONE'); ?></p>
    <?php else : ?>
        <table class="table table-striped">
            <thead><tr>
                <th><?php echo Text::_('COM_ALLIANCEPAY_SITE_PLAN'); ?></th>
                <th><?php echo Text::_('COM_ALLIANCEPAY_SITE_STATUS'); ?></th>
                <th><?php echo Text::_('COM_ALLIANCEPAY_SITE_SINCE'); ?></th>
                <th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($this->rows as $row) : ?>
                <?php $s = $row['subscription']; ?>
                <tr>
                    <td><?php echo $e($row['planName']); ?></td>
                    <td><?php echo $e(Text::_('COM_ALLIANCEPAY_SITE_STATUS_' . $s->status->value)); ?></td>
                    <td><?php echo $e(HTMLHelper::_('date', $s->createdAt, Text::_('DATE_FORMAT_LC4'))); ?></td>
                    <td>
                        <?php if (in_array($s->status->value, ['ACTIVE', 'PENDING'], true) && $s->providerOrderId !== null) : ?>
                            <form method="post" action="<?php echo $e(Route::_('index.php?option=com_alliancepay&task=subscription.cancel')); ?>"
                                  onsubmit="return confirm('<?php echo $e(Text::_('COM_ALLIANCEPAY_SITE_CONFIRM_CANCEL')); ?>');">
                                <input type="hidden" name="id" value="<?php echo (int) $s->id; ?>">
                                <?php echo HTMLHelper::_('form.token'); ?>
                                <button class="btn btn-secondary btn-sm" type="submit"><?php echo Text::_('COM_ALLIANCEPAY_SITE_CANCEL'); ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
