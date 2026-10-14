<?php
/**
 * Structured schedule editor: one form row = one rule (§11.4). Replaces a free-text JSON field.
 *
 * Expects: $editorRows (array), $editorMode ('plan' = amounts + currency, 'template' = stage only),
 *          $editorReadOnly (bool, optional)
 */
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$editorReadOnly = !empty($editorReadOnly);
$isPlan = ($editorMode ?? 'plan') === 'plan';
$units = \Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Domain\RuleValidator::INTERVAL_UNITS;
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$uid = 'rules_' . substr(md5(uniqid('', true)), 0, 6);

$renderRow = static function (string $i, array $r) use ($isPlan, $units, $e, $editorReadOnly): void {
    $dis = $editorReadOnly ? ' disabled' : '';
    ?>
    <tr class="alliance-rule-row">
        <td><input type="number" min="1" class="form-control" name="rules[<?php echo $i; ?>][paymentNumberFrom]" value="<?php echo $e($r['paymentNumberFrom'] ?? ''); ?>"<?php echo $dis; ?>></td>
        <td><input type="number" min="1" class="form-control" name="rules[<?php echo $i; ?>][paymentNumberTo]" value="<?php echo $e($r['paymentNumberTo'] ?? ''); ?>"<?php echo $dis; ?>></td>
        <td><input type="number" min="1" class="form-control" name="rules[<?php echo $i; ?>][intervalValue]" value="<?php echo $e($r['intervalValue'] ?? ''); ?>"<?php echo $dis; ?>></td>
        <td>
            <select class="form-select" name="rules[<?php echo $i; ?>][intervalUnit]"<?php echo $dis; ?>>
                <?php foreach ($units as $u) : ?>
                    <option value="<?php echo $u; ?>"<?php echo strtoupper((string) ($r['intervalUnit'] ?? 'MONTH')) === $u ? ' selected' : ''; ?>><?php echo Text::_('COM_ALLIANCEPAY_UNIT_' . $u); ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <?php if ($isPlan) : ?>
            <td><input type="text" inputmode="decimal" class="form-control alliance-amount" name="rules[<?php echo $i; ?>][amount]" value="<?php echo $e($r['amount'] ?? ''); ?>"<?php echo $dis; ?>></td>
        <?php else : ?>
            <td>
                <select class="form-select" name="rules[<?php echo $i; ?>][stage]"<?php echo $dis; ?>>
                    <option value="paid"<?php echo ($r['stage'] ?? 'paid') === 'paid' ? ' selected' : ''; ?>><?php echo Text::_('COM_ALLIANCEPAY_STAGE_PAID'); ?></option>
                    <option value="trial"<?php echo ($r['stage'] ?? '') === 'trial' ? ' selected' : ''; ?>><?php echo Text::_('COM_ALLIANCEPAY_STAGE_TRIAL'); ?></option>
                </select>
            </td>
        <?php endif; ?>
        <td><?php if (!$editorReadOnly) : ?><button type="button" class="btn btn-sm btn-outline-danger alliance-rule-remove" title="<?php echo Text::_('JACTION_DELETE'); ?>">&times;</button><?php endif; ?></td>
    </tr>
    <?php
};
?>
<table class="table table-sm alliance-rules" id="<?php echo $uid; ?>">
    <thead>
        <tr>
            <th><?php echo Text::_('COM_ALLIANCEPAY_FIELD_PAYMENTNUMBERFROM'); ?></th>
            <th><?php echo Text::_('COM_ALLIANCEPAY_FIELD_PAYMENTNUMBERTO'); ?></th>
            <th><?php echo Text::_('COM_ALLIANCEPAY_FIELD_INTERVALVALUE'); ?></th>
            <th><?php echo Text::_('COM_ALLIANCEPAY_FIELD_INTERVALUNIT'); ?></th>
            <?php if ($isPlan) : ?>
                <th><?php echo Text::_('COM_ALLIANCEPAY_FIELD_AMOUNT'); ?></th>
            <?php else : ?>
                <th><?php echo Text::_('COM_ALLIANCEPAY_FIELD_STAGE'); ?></th>
            <?php endif; ?>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach (array_values($editorRows) as $i => $row) { $renderRow((string) $i, (array) $row); } ?>
    </tbody>
</table>
<?php if (!$editorReadOnly) : ?>
    <button type="button" class="btn btn-sm btn-secondary" data-alliance-add="<?php echo $uid; ?>"><?php echo Text::_('COM_ALLIANCEPAY_ADD_RULE'); ?></button>
    <template id="<?php echo $uid; ?>_tpl"><?php $renderRow('__I__', ['paymentNumberFrom' => '', 'paymentNumberTo' => '', 'intervalValue' => 1, 'intervalUnit' => 'MONTH', 'currency' => '980']); ?></template>
    <script>
    (function () {
        var table = document.getElementById(<?php echo json_encode($uid); ?>);
        var tbody = table.tBodies[0];
        var tpl = document.getElementById(<?php echo json_encode($uid . '_tpl'); ?>);
        function reindex() {
            Array.prototype.forEach.call(tbody.rows, function (tr, i) {
                tr.querySelectorAll('[name^="rules["]').forEach(function (el) {
                    el.name = el.name.replace(/^rules\[[^\]]*\]/, 'rules[' + i + ']');
                });
            });
        }
        document.querySelector('[data-alliance-add="<?php echo $uid; ?>"]').addEventListener('click', function () {
            var holder = document.createElement('tbody');
            holder.innerHTML = tpl.innerHTML.replace(/__I__/g, tbody.rows.length);
            tbody.appendChild(holder.firstElementChild);
            reindex();
        });
        tbody.addEventListener('click', function (ev) {
            if (ev.target.classList.contains('alliance-rule-remove')) {
                ev.target.closest('tr').remove();
                reindex();
            }
        });
        table.alliancePlaceRows = function (rows) {
            tbody.innerHTML = '';
            rows.forEach(function (r) {
                var holder = document.createElement('tbody');
                holder.innerHTML = tpl.innerHTML.replace(/__I__/g, tbody.rows.length);
                var tr = holder.firstElementChild;
                Object.keys(r).forEach(function (k) {
                    var el = tr.querySelector('[name$="[' + k + ']"]');
                    if (el) { el.value = r[k]; }
                });
                tbody.appendChild(tr);
            });
            reindex();
        };
    })();
    </script>
<?php endif; ?>
