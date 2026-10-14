<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

namespace Alliance\Component\Alliancepay\Administrator\View\Subscriptions;

defined('_JEXEC') or die;

use Alliance\Component\Alliancepay\Administrator\Helper\Services;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository\SubscriptionRepository;
use Joomla\CMS\Factory;
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
        $input = Factory::getApplication()->getInput();
        $filter = $input->get('filter', [], 'array');
        $this->filters = [
            'plan_id' => (int) ($filter['plan_id'] ?? 0),
            'status' => (string) ($filter['status'] ?? ''),
            'user_id' => (int) ($filter['user_id'] ?? 0),
        ];
        $this->page = max(1, $input->getInt('page', 1));

        $repository = Services::get(SubscriptionRepository::class);
        $this->total = $repository->count($this->filters);
        $this->items = $repository->search($this->filters, self::PAGE_SIZE, ($this->page - 1) * self::PAGE_SIZE);

        ToolbarHelper::title(Text::_('COM_ALLIANCEPAY_SUBSCRIPTIONS_TITLE'), 'users');

        parent::display($tpl);
    }
}
