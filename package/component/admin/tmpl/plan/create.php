<?php
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var \Alliance\Component\Alliancepay\Administrator\View\Plan\HtmlView $this */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$form = $this->form;
$product = $form['product'] ?? null;
$rows = $this->formRows ?: [['paymentNumberFrom' => 1, 'paymentNumberTo' => 999, 'intervalValue' => 1, 'intervalUnit' => 'MONTH']];
$templatesJson = json_encode(array_map(static fn ($t) => ['id' => $t->id, 'category' => $t->categoryIndicator, 'rules' => $t->rulesTemplate], $this->templates));
$initialProductJson = json_encode($product);
?>
<?php include __DIR__ . '/../_partials/nav.php'; ?>

<form method="post" action="index.php?option=com_alliancepay&amp;task=plan.create" id="alliance-plan-form" class="form-validate">
    <?php echo HTMLHelper::_('form.token'); ?>
    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label" for="product_search"><?php echo Text::_('COM_ALLIANCEPAY_FIELD_PRODUCTID'); ?> *</label>
            <div class="position-relative">
                <input type="hidden" id="product_id" name="product_id" value="<?php echo $e($form['product_id'] ?? ''); ?>">
                <input type="text" class="form-control" id="product_search" autocomplete="off"
                       placeholder="<?php echo $e(Text::_('COM_ALLIANCEPAY_PRODUCT_SEARCH_PLACEHOLDER')); ?>"
                       value="<?php echo $e($product['name'] ?? ''); ?>">
                <div id="product_results" class="list-group position-absolute w-100" style="z-index:1000;display:none;max-height:260px;overflow-y:auto"></div>
            </div>
            <div class="form-text" id="product_hint">
                <?php if ($product === null) : ?>
                    <?php echo Text::_('COM_ALLIANCEPAY_PRODUCT_HINT'); ?>
                <?php elseif ($product['price'] === null) : ?>
                    <?php echo $e($product['name']) . ' — ' . Text::_('COM_ALLIANCEPAY_PRODUCT_NO_UAH_PRICE'); ?>
                <?php else : ?>
                    <?php echo $e($product['name'] . ' — ' . number_format($product['price'], 2) . ' ' . $product['currency']); ?>
                <?php endif; ?>
            </div>
            <div class="invalid-feedback" id="product_error" style="display:none"><?php echo Text::_('COM_ALLIANCEPAY_ERR_PRODUCT_REQUIRED'); ?></div>
        </div>
        <div class="col-md-5">
            <label class="form-label" for="name"><?php echo Text::_('COM_ALLIANCEPAY_FIELD_NAME'); ?> *</label>
            <input type="text" class="form-control" id="name" name="name" value="<?php echo $e($form['name'] ?? ''); ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="category_indicator"><?php echo Text::_('COM_ALLIANCEPAY_FIELD_CATEGORY'); ?> *</label>
            <input type="text" class="form-control" id="category_indicator" name="category_indicator" value="<?php echo $e($form['category_indicator'] ?? ''); ?>" required>
        </div>
        <div class="col-md-8">
            <label class="form-label" for="description"><?php echo Text::_('COM_ALLIANCEPAY_FIELD_DESCRIPTION'); ?></label>
            <input type="text" class="form-control" id="description" name="description" value="<?php echo $e($form['description'] ?? ''); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="template_id"><?php echo Text::_('COM_ALLIANCEPAY_FIELD_TEMPLATE'); ?></label>
            <select class="form-select" id="template_id" name="template_id">
                <option value=""><?php echo Text::_('COM_ALLIANCEPAY_NO_TEMPLATE'); ?></option>
                <?php foreach ($this->templates as $t) : ?>
                    <option value="<?php echo (int) $t->id; ?>"<?php echo (int) ($form['template_id'] ?? 0) === $t->id ? ' selected' : ''; ?>><?php echo $e($t->name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <h3 class="mt-4"><?php echo Text::_('COM_ALLIANCEPAY_SCHEDULE'); ?></h3>
    <p class="text-muted"><?php echo Text::_('COM_ALLIANCEPAY_SCHEDULE_HELP'); ?></p>
    <?php $editorRows = $rows; $editorMode = 'plan'; include __DIR__ . '/../_partials/rules_editor.php'; ?>

    <button class="btn btn-primary mt-3" type="submit"><?php echo Text::_('COM_ALLIANCEPAY_CREATE_PLAN'); ?></button>
    <a class="btn btn-secondary mt-3" href="index.php?option=com_alliancepay&amp;view=plans"><?php echo Text::_('JCANCEL'); ?></a>
</form>

<script>
(function () {
    var templates = <?php echo $templatesJson; ?>;
    var form = document.getElementById('alliance-plan-form');
    var productId = document.getElementById('product_id');
    var search = document.getElementById('product_search');
    var results = document.getElementById('product_results');
    var hint = document.getElementById('product_hint');
    var errorEl = document.getElementById('product_error');
    var nameField = document.getElementById('name');
    var tplSelect = document.getElementById('template_id');
    var table = document.querySelector('#alliance-plan-form table.alliance-rules');
    var initialProduct = <?php echo $initialProductJson; ?>;
    var price = initialProduct ? initialProduct.price : null;

    // Catalog price as an EDITABLE default for every non-trial amount (§11.3). Subscriptions are
    // UAH only (rules_editor.php no longer offers a currency choice), so only the amount is filled.
    function fillAmounts(force) {
        if (price === null) { return; }
        table.querySelectorAll('tbody tr').forEach(function (tr) {
            var amount = tr.querySelector('.alliance-amount');
            if (amount && (force || amount.value === '') && amount.dataset.trial !== '1') { amount.value = price.toFixed(2); }
        });
    }

    function hideResults() { results.style.display = 'none'; results.innerHTML = ''; }

    // A product may have no price row stored in UAH at all (VirtueMart's own currency
    // conversion is not trusted here — see ProductLookup.php); p.price/p.currency are then null
    // and the admin must type the amount by hand, same as the "hint, never a constraint" default.
    function priceLabel(p) {
        return p.price === null
            ? <?php echo json_encode(Text::_('COM_ALLIANCEPAY_PRODUCT_NO_UAH_PRICE')); ?>
            : p.price.toFixed(2) + ' ' + p.currency;
    }

    function selectProduct(p) {
        productId.value = p.id;
        search.value = p.name;
        hint.textContent = p.name + ' — ' + priceLabel(p);
        search.classList.remove('is-invalid');
        errorEl.style.display = 'none';
        if (p.price !== null) { price = p.price; }
        if (!nameField.value) { nameField.value = p.name; }
        fillAmounts(false);
        hideResults();
    }

    function renderResults(items) {
        if (!items.length) {
            results.innerHTML = '<span class="list-group-item text-muted">' + <?php echo json_encode(Text::_('COM_ALLIANCEPAY_PRODUCT_NOT_FOUND')); ?> + '</span>';
            results.style.display = '';
            return;
        }
        results.innerHTML = '';
        items.forEach(function (p) {
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item list-group-item-action';
            item.textContent = p.name + ' (#' + p.id + ') — ' + priceLabel(p);
            item.addEventListener('click', function () { selectProduct(p); });
            results.appendChild(item);
        });
        results.style.display = '';
    }

    var searchTimer = null;
    search.addEventListener('input', function () {
        productId.value = '';
        search.classList.remove('is-invalid');
        errorEl.style.display = 'none';
        var term = search.value.trim();
        window.clearTimeout(searchTimer);
        if (term.length < 2) { hideResults(); return; }
        searchTimer = window.setTimeout(function () {
            fetch('index.php?option=com_alliancepay&task=plan.searchProducts&format=json&term=' + encodeURIComponent(term), {credentials: 'same-origin'})
                .then(function (r) { return r.json(); })
                .then(renderResults);
        }, 300);
    });

    document.addEventListener('click', function (ev) {
        if (ev.target !== search && !results.contains(ev.target)) { hideResults(); }
    });

    form.addEventListener('submit', function (ev) {
        if (!productId.value) {
            ev.preventDefault();
            search.classList.add('is-invalid');
            errorEl.style.display = 'block';
            search.focus();
        }
    });

    tplSelect.addEventListener('change', function () {
        var t = templates.filter(function (x) { return String(x.id) === tplSelect.value; })[0];
        if (!t) { return; }
        var category = document.getElementById('category_indicator');
        if (!category.value && t.category) { category.value = t.category; }
        table.alliancePlaceRows(t.rules.map(function (r) {
            return {
                paymentNumberFrom: r.paymentNumberFrom, paymentNumberTo: r.paymentNumberTo,
                intervalValue: r.intervalValue, intervalUnit: r.intervalUnit,
                amount: r.stage === 'trial' ? '0.00' : (price === null ? '' : price.toFixed(2))
            };
        }));
    });
})();
</script>
