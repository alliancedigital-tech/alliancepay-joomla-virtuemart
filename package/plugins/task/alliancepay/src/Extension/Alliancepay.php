<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

namespace Alliance\Plugin\Task\Alliancepay\Extension;

\defined('_JEXEC') or die;

use Alliance\Plugin\Vmpayment\Alliancepay\Services\Bootstrap\ServiceRegistry;
use Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Service\PlanArchiveService;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;
use Throwable;

/**
 * Task: finds plans that are switched off for new customers and have no active subscribers left,
 * and marks them as ARCHIVE CANDIDATES (§4.7). It never archives anything at the provider by itself —
 * that stays an explicit administrator decision.
 *
 * @since 1.5.0
 */
final class Alliancepay extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    private const TASKS_MAP = [
        'alliancepay.archive_candidates' => [
            'langConstPrefix' => 'PLG_TASK_ALLIANCEPAY_ARCHIVE_CANDIDATES',
            'method' => 'markArchiveCandidates',
        ],
    ];

    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList' => 'advertiseRoutines',
            'onExecuteTask' => 'standardRoutineHandler',
        ];
    }

    private function markArchiveCandidates(ExecuteTaskEvent $event): int
    {
        try {
            if (!class_exists(ServiceRegistry::class)) {
                $autoload = JPATH_PLUGINS . '/vmpayment/alliancepay/vendor/autoload.php';

                if (is_file($autoload)) {
                    require_once $autoload;
                }
            }

            $marked = ServiceRegistry::createContainer()->get(PlanArchiveService::class)->markCandidates();
            $this->logTask('Marked ' . $marked . ' plan(s) as archive candidates');

            return Status::OK;
        } catch (Throwable $e) {
            Log::add('task alliancepay: ' . $e->getMessage(), Log::ERROR, 'alliance_payment');
            $this->logTask('Failed: ' . $e->getMessage(), 'error');

            return Status::KNOCKOUT;
        }
    }
}
