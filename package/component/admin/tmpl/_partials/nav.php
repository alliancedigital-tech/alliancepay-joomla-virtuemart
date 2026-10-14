<?php
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$current = \Joomla\CMS\Factory::getApplication()->getInput()->getCmd('view', 'plans');
$tabs = [
    'plans' => 'COM_ALLIANCEPAY_MENU_PLANS',
    'templates' => 'COM_ALLIANCEPAY_MENU_TEMPLATES',
    'subscriptions' => 'COM_ALLIANCEPAY_MENU_SUBSCRIPTIONS',
    'audit' => 'COM_ALLIANCEPAY_MENU_AUDIT',
];
$group = ['plan' => 'plans', 'template' => 'templates', 'subscription' => 'subscriptions'][$current] ?? $current;
?>
<ul class="nav nav-tabs mb-3">
    <?php foreach ($tabs as $view => $label) : ?>
        <li class="nav-item">
            <a class="nav-link<?php echo $view === $group ? ' active' : ''; ?>"
               href="index.php?option=com_alliancepay&amp;view=<?php echo $view; ?>"><?php echo Text::_($label); ?></a>
        </li>
    <?php endforeach; ?>
</ul>
