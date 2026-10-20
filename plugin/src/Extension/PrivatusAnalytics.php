<?php

/**
 * @package     Privatus Analytics for Joomla
 * @copyright   (C) 2026 Two Phase LLC
 * @license     GNU General Public License version 2 or later
 */

namespace TwoPhase\Plugin\System\PrivatusAnalytics\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use TwoPhase\Plugin\System\PrivatusAnalytics\Helper\TagBuilder;

/**
 * Adds the cookieless Privatus Analytics tracking script to the site frontend.
 *
 * The plugin only prints the browser script. It never sends events from the
 * server and sets no cookies.
 */
final class PrivatusAnalytics extends CMSPlugin implements SubscriberInterface
{
    /**
     * Returns the events this plugin listens to.
     *
     * @return  array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return ['onBeforeCompileHead' => 'onBeforeCompileHead'];
    }

    /**
     * Adds the tracker script to the document head.
     *
     * @param   Event  $event  The event.
     *
     * @return  void
     */
    public function onBeforeCompileHead(Event $event): void
    {
        $app = $this->getApplication();

        // Frontend only. Never the administrator, API or CLI.
        if (!$app->isClient('site')) {
            return;
        }

        $document = $app->getDocument();

        if (!($document instanceof HtmlDocument)) {
            return;
        }

        $siteId = TagBuilder::cleanSiteId((string) $this->params->get('site_id', ''));

        if ($siteId === '') {
            return;
        }

        if ($this->isSkippedUser($app->getIdentity())) {
            return;
        }

        $attributes = TagBuilder::scriptAttributes($this->params->toArray());

        $document->getWebAssetManager()->registerAndUseScript(
            'plg_system_privatusanalytics.tracker',
            $attributes['src'],
            ['version' => false],
            self::withoutSrc($attributes)
        );
    }

    /**
     * Whether the signed-in user should be left out of the reports.
     *
     * @param   object|null  $user  The current user.
     *
     * @return  boolean
     */
    private function isSkippedUser(?object $user): bool
    {
        if ($user === null || empty($user->id)) {
            return false;
        }

        if ((int) $this->params->get('skip_logged_in', 0) === 1) {
            return true;
        }

        $skipGroups = array_map('intval', (array) $this->params->get('skip_groups', []));

        if ($skipGroups === []) {
            return false;
        }

        // getAuthorisedGroups() includes groups inherited from parents.
        return array_intersect($skipGroups, array_map('intval', $user->getAuthorisedGroups())) !== [];
    }

    /**
     * Returns the attributes without the src key.
     *
     * @param   array  $attributes  The attributes.
     *
     * @return  array
     */
    private static function withoutSrc(array $attributes): array
    {
        unset($attributes['src']);

        return $attributes;
    }
}
