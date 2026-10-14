<?php
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var \Alliance\Component\Alliancepay\Administrator\View\Template\HtmlView $this */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$form = $this->form;
$isNew = empty($form['id']);
$rows = $this->formRows ?: [
    ['paymentNumberFrom' => 1, 'paymentNumberTo' => 1, 'intervalValue' => 14, 'intervalUnit' => 'DAY', 'stage' => 'trial'],
    ['paymentNumberFrom' => 2, 'paymentNumberTo' => 999, 'intervalValue' => 1, 'intervalUnit' => 'MONTH', 'stage' => 'paid'],
];
?>
<?php include __DIR__ . '/../_partials/nav.php'; ?>

<?php if ($this->readOnly) : ?>
    <div class="alert alert-info"><?php echo Text::_('COM_ALLIANCEPAY_TEMPLATE_READONLY'); ?></div>
<?php endif; ?>

<form method="post" action="index.php?option=com_alliancepay&amp;task=template.save">
    <?php echo HTMLHelper::_('form.token'); ?>
    <input type="hidden" name="id" value="<?php echo (int) ($form['id'] ?? 0); ?>">
    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label" for="code"><?php echo Text::_('COM_ALLIANCEPAY_FIELD_CODE'); ?> *</label>
            <input type="text" class="form-control" id="code" name="code" value="<?php echo $e($form['code'] ?? ''); ?>" <?php echo $isNew ? 'required' : 'readonly'; ?>>
        </div>
        <div class="col-md-5">
            <label class="form-label" for="name"><?php echo Text::_('COM_ALLIANCEPAY_FIELD_NAME'); ?> *</label>
            <input type="text" class="form-control" id="name" name="name" value="<?php echo $e($form['name'] ?? ''); ?>" required <?php echo $this->readOnly ? 'readonly' : ''; ?>>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="category_indicator"><?php echo Text::_('COM_ALLIANCEPAY_FIELD_CATEGORY'); ?></label>
            <input type="text" class="form-control" id="category_indicator" name="category_indicator" value="<?php echo $e($form['category_indicator'] ?? ''); ?>" <?php echo $this->readOnly ? 'readonly' : ''; ?>>
        </div>
    </div>
    <h3 class="mt-4"><?php echo Text::_('COM_ALLIANCEPAY_SCHEDULE_STRUCTURE'); ?></h3>
    <p class="text-muted"><?php echo Text::_('COM_ALLIANCEPAY_TEMPLATE_HELP'); ?></p>
    <?php $editorRows = $rows; $editorMode = 'template'; $editorReadOnly = $this->readOnly; include __DIR__ . '/../_partials/rules_editor.php'; ?>

    <?php if (!$this->readOnly) : ?>
        <button class="btn btn-primary mt-3" type="submit"><?php echo Text::_('JSAVE'); ?></button>
    <?php endif; ?>
    <a class="btn btn-secondary mt-3" href="index.php?option=com_alliancepay&amp;view=templates"><?php echo Text::_('JCANCEL'); ?></a>
</form>
