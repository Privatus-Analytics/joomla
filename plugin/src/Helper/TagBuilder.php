<?php

/**
 * @package     Privatus Analytics for Joomla
 * @copyright   (C) 2026 Two Phase LLC
 * @license     GNU General Public License version 2 or later
 */

namespace TwoPhase\Plugin\System\PrivatusAnalytics\Helper;

\defined('_JEXEC') or die;

/**
 * Turns plugin parameters into tracker script attributes.
 *
 * It has no Joomla dependencies, so it can be tested on its own.
 */
final class TagBuilder
{
    public const DEFAULT_HOST = 'https://privatusstats.com';

    public const MODULES = ['engage', 'auto', 'vitals', 'clicks', 'ask'];

    public const SPA_MODES = ['auto', 'history', 'hash', 'off'];

    /**
     * Keeps only characters a site id can contain.
     *
     * @param   string  $value  The raw value.
     *
     * @return  string
     */
    public static function cleanSiteId(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]/', '', trim($value)) ?? '';
    }

    /**
     * Builds the script tag attributes from the plugin parameters.
     *
     * @param   array  $params  The plugin parameters.
     *
     * @return  array  Attribute name to value. The src key holds the script URL.
     */
    public static function scriptAttributes(array $params): array
    {
        $host = rtrim(trim((string) ($params['script_host'] ?? '')), '/');

        if ($host === '' || !preg_match('#^https?://#i', $host)) {
            $host = self::DEFAULT_HOST;
        }

        $attributes = [
            'src' => $host . '/js/pa.js',
            'defer' => true,
            'data-site' => self::cleanSiteId((string) ($params['site_id'] ?? '')),
        ];

        // Ticking nothing keeps the tracker default. Setting the attribute replaces it.
        $modules = array_values(array_intersect(self::MODULES, array_map('strval', (array) ($params['modules'] ?? []))));

        if ($modules !== []) {
            $attributes['data-modules'] = implode(',', $modules);
        }

        $exclude = self::splitList((string) ($params['exclude'] ?? ''));

        if ($exclude !== []) {
            $attributes['data-exclude'] = implode(',', $exclude);
        }

        $domains = self::splitList((string) ($params['domains'] ?? ''));

        if ($domains !== []) {
            $attributes['data-domains'] = implode(',', $domains);
        }

        $spa = (string) ($params['spa'] ?? 'auto');

        if ($spa !== 'auto' && \in_array($spa, self::SPA_MODES, true)) {
            $attributes['data-spa'] = $spa;
        }

        if ((int) ($params['manual'] ?? 0) === 1) {
            $attributes['data-manual'] = 'true';
        }

        return $attributes;
    }

    /**
     * Splits a comma or line separated list into clean items.
     *
     * @param   string  $value  The raw value.
     *
     * @return  string[]
     */
    public static function splitList(string $value): array
    {
        $items = preg_split('/[\s,]+/', $value) ?: [];

        return array_values(array_filter(array_map('trim', $items), static fn ($item) => $item !== ''));
    }
}
