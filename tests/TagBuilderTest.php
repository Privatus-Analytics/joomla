<?php

/**
 * Plain PHP tests for the tag builder. Run with: php tests/TagBuilderTest.php
 */

define('_JEXEC', 1);

require __DIR__ . '/../plugin/src/Helper/TagBuilder.php';

use TwoPhase\Plugin\System\PrivatusAnalytics\Helper\TagBuilder;

$failures = 0;

function check(string $name, $expected, $actual): void
{
    global $failures;

    if ($expected !== $actual) {
        $failures++;
        echo "FAIL $name\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
    } else {
        echo "ok   $name\n";
    }
}

check('basic', [
    'src' => 'https://privatusstats.com/js/pa.js',
    'defer' => true,
    'data-site' => 'pa_TEST123456',
], TagBuilder::scriptAttributes(['site_id' => ' pa_TEST123456 ']));

check('host trailing slash and custom host', 'https://stats.example.com/js/pa.js',
    TagBuilder::scriptAttributes(['site_id' => 'pa_A', 'script_host' => 'https://stats.example.com/'])['src']);

check('bad host falls back', 'https://privatusstats.com/js/pa.js',
    TagBuilder::scriptAttributes(['site_id' => 'pa_A', 'script_host' => 'javascript:alert(1)'])['src']);

check('site id is cleaned', 'pa_ABCscript', TagBuilder::cleanSiteId('pa_ABC"><script>'));

check('modules value', 'engage,auto',
    TagBuilder::scriptAttributes(['site_id' => 'pa_A', 'modules' => ['auto', 'bogus', 'engage']])['data-modules']);

check('no modules keeps the default', false,
    isset(TagBuilder::scriptAttributes(['site_id' => 'pa_A', 'modules' => []])['data-modules']));

check('exclude list from lines and commas', '/a/*,/b,/c',
    TagBuilder::scriptAttributes(['site_id' => 'pa_A', 'exclude' => "/a/*, /b\n/c\n"])['data-exclude']);

check('domains', 'example.com,www.example.com',
    TagBuilder::scriptAttributes(['site_id' => 'pa_A', 'domains' => 'example.com, www.example.com'])['data-domains']);

check('spa auto is omitted', false,
    isset(TagBuilder::scriptAttributes(['site_id' => 'pa_A', 'spa' => 'auto'])['data-spa']));

check('spa hash', 'hash', TagBuilder::scriptAttributes(['site_id' => 'pa_A', 'spa' => 'hash'])['data-spa']);

check('spa unknown is omitted', false,
    isset(TagBuilder::scriptAttributes(['site_id' => 'pa_A', 'spa' => 'weird'])['data-spa']));

check('manual', 'true', TagBuilder::scriptAttributes(['site_id' => 'pa_A', 'manual' => '1'])['data-manual']);

check('manual off is omitted', false,
    isset(TagBuilder::scriptAttributes(['site_id' => 'pa_A', 'manual' => '0'])['data-manual']));

exit($failures === 0 ? 0 : 1);
