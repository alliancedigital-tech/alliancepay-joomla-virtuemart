<?php
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;

/** @var array $viewData */
$product = $viewData['product'];
$group = $viewData['group'];
$plans = $viewData['plans'];
$formatter = $viewData['formatter'];
$isGuest = !empty($viewData['isGuest']);
$showDetails = ($group->show_plan_details ?? 1) != 0;

$base = 'customProductData[' . (int) $product->virtuemart_product_id . '][' . (int) $group->virtuemart_custom_id
    . '][' . (int) $group->virtuemart_customfield_id . ']';
$uid = 'alsub_' . (int) $product->virtuemart_product_id . '_' . (int) $group->virtuemart_customfield_id;
$unitKey = ['DAY' => 'DAY', 'WEEK' => 'WEEK', 'MONTH' => 'MONTH'];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="alliancepay-subscription-choice" id="<?php echo $e($uid); ?>">
    <label>
        <input type="radio" name="<?php echo $e($base . '[mode]'); ?>" value="once" checked>
        <?php echo Text::_('PLG_VMCUSTOM_ALLIANCEPAY_SUBSCRIPTION_ONCE'); ?>
    </label><br>
    <label>
        <input type="radio" name="<?php echo $e($base . '[mode]'); ?>" value="subscription"
            <?php echo $isGuest ? 'disabled' : ''; ?>>
        <?php echo Text::_('PLG_VMCUSTOM_ALLIANCEPAY_SUBSCRIPTION_SUBSCRIBE'); ?>
    </label>
    <?php if ($isGuest) : ?>
        <div class="alliancepay-subscription-login">
            <?php echo Text::_('VMPAYMENT_ALLIANCEPAY_SUB_ERR_GUEST'); ?>
        </div>
    <?php else : ?>
        <div class="alliancepay-subscription-plans" style="display:none">
            <?php foreach ($plans as $i => $plan) : ?>
                <label style="display:block">
                    <input type="radio" name="<?php echo $e($base . '[plan_id]'); ?>" value="<?php echo (int) $plan->id; ?>"
                        <?php echo $i === 0 ? 'checked' : ''; ?>>
                    <strong><?php echo $e($plan->name); ?></strong>
                    <?php if ($showDetails) : ?>
                        <?php foreach ($formatter->describe($plan->rules) as $row) : ?>
                            <div class="alliancepay-plan-row">
                                <?php
                                $range = $row['from'] === $row['to'] ? (string) $row['from'] : $row['from'] . '–' . ($row['to'] >= 999 ? '∞' : $row['to']);
                                echo $e(Text::sprintf(
                                    $row['isTrial'] ? 'PLG_VMCUSTOM_ALLIANCEPAY_SUBSCRIPTION_ROW_FREE' : 'PLG_VMCUSTOM_ALLIANCEPAY_SUBSCRIPTION_ROW',
                                    $range,
                                    number_format($row['amount'], 2, '.', ' ') . ' ' . $row['currency'],
                                    $row['intervalValue'],
                                    Text::_('PLG_VMCUSTOM_ALLIANCEPAY_SUBSCRIPTION_UNIT_' . ($unitKey[$row['intervalUnit']] ?? 'MONTH'))
                                ));
                                ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<script>
(function () {
    var root = document.getElementById(<?php echo json_encode($uid); ?>);
    if (!root) { return; }
    var plans = root.querySelector('.alliancepay-subscription-plans');
    function sync() {
        var mode = root.querySelector('input[value="subscription"]');
        var planInputs = root.querySelectorAll('.alliancepay-subscription-plans input');
        var on = mode && mode.checked;
        if (plans) { plans.style.display = on ? '' : 'none'; }
        planInputs.forEach(function (i) { i.disabled = !on; });
    }
    root.addEventListener('change', sync);
    sync();
})();
</script>
