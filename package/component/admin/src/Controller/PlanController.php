<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

namespace Alliance\Component\Alliancepay\Administrator\Controller;

defined('_JEXEC') or die;

use Alliance\Component\Alliancepay\Administrator\Helper\ErrorFormatter;
use Alliance\Component\Alliancepay\Administrator\Helper\ProductLookup;
use Alliance\Component\Alliancepay\Administrator\Helper\RulesInput;
use Alliance\Component\Alliancepay\Administrator\Helper\Services;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Http\ProviderApiException;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\PlanAlreadyExistsException;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\PlanArchiveService;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\PlanService;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\PlanSyncException;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\PlanValidationException;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Throwable;

/**
 * Class PlanController.
 */
class PlanController extends BaseController
{
    private const FORM_STATE = 'com_alliancepay.plan.form';

    public function create(): void
    {
        $this->checkToken();

        $input = $this->input->post;
        $rules = RulesInput::fromPost($input->get('rules', [], 'array'));
        $form = [
            'product_id' => $input->getInt('product_id'),
            'name' => $input->getString('name'),
            'description' => $input->getString('description'),
            'category_indicator' => $input->getString('category_indicator'),
            'template_id' => $input->getInt('template_id') ?: null,
            'rules' => $rules,
        ];

        try {
            $plan = Services::get(PlanService::class)->createPlan(
                (int) $form['product_id'],
                $form['name'],
                $form['description'] !== '' ? $form['description'] : null,
                $form['category_indicator'],
                $rules,
                $form['template_id']
            );
            $this->app->setUserState(self::FORM_STATE, null);
            $this->setRedirect($this->planUrl($plan->id), Text::_('COM_ALLIANCEPAY_PLAN_CREATED'));
        } catch (PlanValidationException $e) {
            $this->failWithForm($form, ErrorFormatter::format($e->getErrors()));
        } catch (PlanAlreadyExistsException $e) {
            $existing = $e->getExistingPlan();
            $this->app->setUserState(self::FORM_STATE, null);
            $this->setRedirect(
                $existing ? $this->planUrl($existing->id) : $this->listUrl(),
                Text::_($existing ? 'COM_ALLIANCEPAY_ERR_PLAN_EXISTS' : 'COM_ALLIANCEPAY_ERR_PLAN_IN_PROGRESS'),
                'warning'
            );
        } catch (PlanSyncException $e) {
            $this->app->setUserState(self::FORM_STATE, null);
            $this->setRedirect(
                $this->planUrl($e->getPlan()->id),
                Text::sprintf('COM_ALLIANCEPAY_ERR_PLAN_SYNC', $e->getMessage()),
                'error'
            );
        } catch (ProviderApiException $e) {
            $this->failWithForm($form, [Text::sprintf('COM_ALLIANCEPAY_ERR_PROVIDER', $e->getAdminMessage())]);
        } catch (Throwable $e) {
            $this->failWithForm($form, [$e->getMessage()]);
        }
    }

    public function retrySync(): void
    {
        $this->checkToken();
        $planId = $this->input->post->getInt('id');

        try {
            Services::get(PlanService::class)->retrySync($planId);
            $this->setRedirect($this->planUrl($planId), Text::_('COM_ALLIANCEPAY_PLAN_SYNCED'));
        } catch (Throwable $e) {
            $this->setRedirect($this->planUrl($planId), Text::sprintf('COM_ALLIANCEPAY_ERR_PLAN_SYNC', $e->getMessage()), 'error');
        }
    }

    /**
     * @return void
     */
    public function setOffered(): void
    {
        $this->checkToken();
        $planId = $this->input->post->getInt('id');
        $offered = $this->input->post->getInt('offered') === 1;

        Services::get(PlanService::class)->setOfferedToNewCustomers($planId, $offered);
        $this->setRedirect($this->planUrl($planId), Text::_($offered ? 'COM_ALLIANCEPAY_PLAN_OFFERED' : 'COM_ALLIANCEPAY_PLAN_NOT_OFFERED'));
    }

    /**
     * @return void
     */
    public function reprice(): void
    {
        $this->checkToken();
        $planId = $this->input->post->getInt('id');

        if (!$this->app->getIdentity()->authorise('alliancepay.reprice', 'com_alliancepay')) {
            $this->setRedirect($this->planUrl($planId), Text::_('JERROR_ALERTNOAUTHOR'), 'error');

            return;
        }

        $input = $this->input->post;
        $appliesFrom = trim($input->getString('applies_from'));
        $appliesFromUtc = $appliesFrom !== ''
            ? gmdate('Y-m-d H:i:s', (int) strtotime($appliesFrom . ' ' . $this->app->get('offset', 'UTC')))
            : '';

        try {
            Services::get(PlanService::class)->reprice(
                $planId,
                RulesInput::fromPost($input->get('rules', [], 'array')),
                $appliesFromUtc,
                $input->getInt('customers_notified') === 1,
                $input->getString('reason'),
                (int) $this->app->getIdentity()->id
            );
            $this->setRedirect($this->planUrl($planId), Text::_('COM_ALLIANCEPAY_PLAN_REPRICED'));
        } catch (PlanValidationException $e) {
            $this->setRedirect(
                $this->planUrl($planId),
                implode('<br>', array_map('htmlspecialchars', ErrorFormatter::format($e->getErrors()))),
                'error'
            );
        } catch (PlanAlreadyExistsException $e) {
            $this->setRedirect($this->planUrl($planId), Text::_('COM_ALLIANCEPAY_ERR_PLAN_EXISTS'), 'error');
        } catch (PlanSyncException $e) {
            $this->setRedirect($this->planUrl($planId), Text::sprintf('COM_ALLIANCEPAY_ERR_PLAN_SYNC', $e->getMessage()), 'error');
        } catch (Throwable $e) {
            $this->setRedirect($this->planUrl($planId), Text::sprintf('COM_ALLIANCEPAY_ERR_PROVIDER', $e->getMessage()), 'error');
        }
    }

    /**
     * @return void
     */
    public function confirmArchive(): void
    {
        $this->checkToken();
        $planId = $this->input->post->getInt('id');

        if (!$this->app->getIdentity()->authorise('alliancepay.archive', 'com_alliancepay')) {
            $this->setRedirect($this->planUrl($planId), Text::_('JERROR_ALERTNOAUTHOR'), 'error');

            return;
        }

        try {
            Services::get(PlanArchiveService::class)->confirmArchive($planId, (int) $this->app->getIdentity()->id);
            $this->setRedirect($this->planUrl($planId), Text::_('COM_ALLIANCEPAY_PLAN_ARCHIVED'));
        } catch (Throwable $e) {
            $this->setRedirect($this->planUrl($planId), Text::sprintf('COM_ALLIANCEPAY_ERR_PROVIDER', $e->getMessage()), 'error');
        }
    }

    /**
     * @return void
     */
    public function productPrice(): void
    {
        $product = ProductLookup::find($this->input->getInt('product_id'));

        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $this->app->sendHeaders();
        echo json_encode($product ?? ['error' => true]);
        $this->app->close();
    }

    /**
     * Search-as-you-type product lookup for the plan creation form (§11.3).
     *
     * @return void
     */
    public function searchProducts(): void
    {
        $term = $this->input->getString('term', '');

        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $this->app->sendHeaders();
        echo json_encode(ProductLookup::search($term));
        $this->app->close();
    }

    private function failWithForm(array $form, array $messages): void
    {
        $this->app->setUserState(self::FORM_STATE, $form);
        $this->setRedirect(
            Route::_('index.php?option=com_alliancepay&view=plan&layout=create', false),
            implode('<br>', array_map('htmlspecialchars', $messages)),
            'error'
        );
    }

    private function planUrl(int $id): string
    {
        return Route::_('index.php?option=com_alliancepay&view=plan&id=' . $id, false);
    }

    private function listUrl(): string
    {
        return Route::_('index.php?option=com_alliancepay&view=plans', false);
    }
}
