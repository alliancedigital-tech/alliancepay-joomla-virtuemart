<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

namespace Alliance\Component\Alliancepay\Administrator\Controller;

defined('_JEXEC') or die;

use Alliance\Component\Alliancepay\Administrator\Helper\ErrorFormatter;
use Alliance\Component\Alliancepay\Administrator\Helper\RulesInput;
use Alliance\Component\Alliancepay\Administrator\Helper\Services;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\PlanValidationException;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\TemplateInUseException;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\TemplateService;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Throwable;

/**
 * @since 1.5.0
 */
class TemplateController extends BaseController
{
    private const FORM_STATE = 'com_alliancepay.template.form';

    public function save(): void
    {
        $this->checkToken();

        $input = $this->input->post;
        $id = $input->getInt('id');
        $form = [
            'id' => $id,
            'code' => $input->getString('code'),
            'name' => $input->getString('name'),
            'category_indicator' => $input->getString('category_indicator'),
            'rules' => RulesInput::fromPost($input->get('rules', [], 'array'), false),
        ];

        try {
            $service = Services::get(TemplateService::class);

            if ($id > 0) {
                $service->update($id, $form['name'], $form['category_indicator'], $form['rules']);
            } else {
                $service->create($form['code'], $form['name'], $form['category_indicator'], $form['rules']);
            }

            $this->app->setUserState(self::FORM_STATE, null);
            $this->setRedirect(Route::_('index.php?option=com_alliancepay&view=templates', false), Text::_('COM_ALLIANCEPAY_TEMPLATE_SAVED'));
        } catch (PlanValidationException $e) {
            $this->fail($form, ErrorFormatter::format($e->getErrors()));
        } catch (TemplateInUseException $e) {
            $this->fail($form, [Text::_('COM_ALLIANCEPAY_ERR_TEMPLATE_IN_USE')]);
        } catch (Throwable $e) {
            $this->fail($form, [$e->getMessage()]);
        }
    }

    public function archive(): void
    {
        $this->checkToken();
        Services::get(TemplateService::class)->archive($this->input->post->getInt('id'));
        $this->setRedirect(Route::_('index.php?option=com_alliancepay&view=templates', false), Text::_('COM_ALLIANCEPAY_TEMPLATE_ARCHIVED'));
    }

    private function fail(array $form, array $messages): void
    {
        $this->app->setUserState(self::FORM_STATE, $form);
        $this->setRedirect(
            Route::_('index.php?option=com_alliancepay&view=template' . ($form['id'] ? '&id=' . (int) $form['id'] : ''), false),
            implode('<br>', array_map('htmlspecialchars', $messages)),
            'error'
        );
    }
}
