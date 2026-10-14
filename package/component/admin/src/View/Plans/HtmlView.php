<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

namespace Alliance\Component\Alliancepay\Administrator\View\Plans;

defined('_JEXEC') or die;

use Alliance\Component\Alliancepay\Administrator\Helper\Services;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\PlanRepository;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * @since 1.5.0
 */
class HtmlView extends BaseHtmlView
{
    public const PAGE_SIZE = 50;

    public array $items = [];
    public int $total = 0;
    public int $page = 1;
    public array $filters = [];

    public function display($tpl = null): void
    {
        $input = \Joomla\CMS\Factory::getApplication()->getInput();
        $filter = $input->get('filter', [], 'array');
        $this->filters = [
            'product_id' => (int) ($filter['product_id'] ?? 0),
            'status' => (string) ($filter['status'] ?? ''),
            'is_active' => (string) ($filter['is_active'] ?? ''),
        ];
        $this->page = max(1, $input->getInt('page', 1));

        $repository = Services::get(PlanRepository::class);
        $this->total = $repository->count($this->filters);
        $this->items = $repository->search($this->filters, self::PAGE_SIZE, ($this->page - 1) * self::PAGE_SIZE);

        ToolbarHelper::title(Text::_('COM_ALLIANCEPAY_PLANS_TITLE'), 'list');

        parent::display($tpl);
    }
}
