<?php
/**
 * RecordDataFormatter Test Class
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2016.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\View\Helper\Root;

use VuFind\View\Helper\Root\RecordDataFormatter;
use VuFind\View\Helper\Root\RecordDataFormatterFactory;

/**
 * RecordDataFormatter Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class RecordDataFormatterTest extends \VuFindTest\Unit\ViewHelperTestCase
{
    /**
     * Get view helpers needed by test.
     *
     * @return array
     */
    protected function getViewHelpers()
    {
        $context = new \VuFind\View\Helper\Root\Context();
        return [
            'auth' => new \VuFind\View\Helper\Root\Auth(
                $this->getMockBuilder('VuFind\Auth\Manager')->disableOriginalConstructor()->getMock(),
                $this->getMockBuilder('VuFind\Auth\ILSAuthenticator')->disableOriginalConstructor()->getMock()
            ),
            'context' => $context,
            'openUrl' => new \VuFind\View\Helper\Root\OpenUrl($context, [], $this->getMockBuilder('VuFind\Resolver\Driver\PluginManager')->disableOriginalConstructor()->getMock()),
            'proxyUrl' => new \VuFind\View\Helper\Root\ProxyUrl(),
            'record' => new \VuFind\View\Helper\Root\Record(),
            'recordLink' => new \VuFind\View\Helper\Root\RecordLink($this->getMockBuilder('VuFind\Record\Router')->disableOriginalConstructor()->getMock()),
            'searchTabs' => $this->getMockBuilder('VuFind\View\Helper\Root\SearchTabs')->disableOriginalConstructor()->getMock(),
            'transEsc' => new \VuFind\View\Helper\Root\TransEsc(),
            'translate' => new \VuFind\View\Helper\Root\Translate(),
            'usertags' => new \VuFind\View\Helper\Root\UserTags(),
        ];
    }

    /**
     * Get a record driver with fake data.
     *
     * @param array $overrides Fixture fields to override.
     *
     * @return SolrDefault
     */
    protected function getDriver($overrides = [])
    {
        // "Mock out" tag functionality to avoid database access:
        $methods = [
            'getBuilding', 'getDeduplicatedAuthors', 'getContainerTitle', 'getTags'
        ];
        $record = $this->getMockBuilder('VuFind\RecordDriver\SolrDefault')
            ->setMethods($methods)
            ->getMock();
        $record->expects($this->any())->method('getTags')
            ->will($this->returnValue([]));
        // Force a return value of zero so we can test this edge case value (even
        // though in the context of "building"/"container title" it makes no sense):
        $record->expects($this->any())->method('getBuilding')
            ->will($this->returnValue(0));
        $record->expects($this->any())->method('getContainerTitle')
            ->will($this->returnValue('0'));
        // Expect only one call to getDeduplicatedAuthors to confirm that caching
        // works correctly (we need this data more than once, but should only pull
        // it from the driver once).
        $authors = [
            'primary' => ['Vico, Giambattista, 1668-1744.' => []],
            'secondary' => ['Pandolfi, Claudia.' => []],
        ];
        $record->expects($this->once())->method('getDeduplicatedAuthors')
            ->will($this->returnValue($authors));

        // Load record data from fixture file:
        $fixture = json_decode(
            file_get_contents(
                realpath(
                    VUFIND_PHPUNIT_MODULE_PATH . '/fixtures/misc/testbug2.json'
                )
            ),
            true
        );
        $record->setRawData($overrides + $fixture['response']['docs'][0]);
        return $record;
    }

    /**
     * Build a formatter, including necessary mock view w/ helpers.
     *
     * @return RecordDataFormatter
     */
    protected function getFormatter()
    {
        // Build the formatter:
        $factory = new RecordDataFormatterFactory();
        $formatter = $factory->__invoke(
            $this->getServiceManager(), RecordDataFormatter::class
        );

        // Create a view object with a set of helpers:
        $helpers = $this->getViewHelpers();
        $view = $this->getPhpRenderer($helpers);

        // Mock out the router to avoid errors:
        $match = new \Zend\Router\RouteMatch([]);
        $match->setMatchedRouteName('foo');
        $view->plugin('url')
            ->setRouter($this->createMock('Zend\Router\RouteStackInterface'))
            ->setRouteMatch($match);

        // Inject the view object into all of the helpers:
        $formatter->setView($view);
        foreach ($helpers as $helper) {
            $helper->setView($view);
        }

        return $formatter;
    }

    /**
     * Find a result in the results array.
     *
     * @param string $needle   Result to look up.
     * @param array  $haystack Result set.
     *
     * @return mixed
     */
    protected function findResult($needle, $haystack)
    {
        foreach ($haystack as $current) {
            if ($current['label'] == $needle) {
                return $current;
            }
        }
        return null;
    }

    /**
     * Extract labels from a results array.
     *
     * @param array $results Results to process.
     *
     * @return array
     */
    protected function getLabels($results)
    {
        $callback = function ($c) {
            return $c['label'];
        };
        return array_map($callback, $results);
    }

    /**
     * Test citation generation
     *
     * @return void
     */
    public function testFormatting()
    {
        $formatter = $this->getFormatter();
        $spec = $formatter->getDefaults('core');
        $spec['Building'] = [
            'dataMethod' => 'getBuilding', 'pos' => 0, 'context' => ['foo' => 1],
            'translationTextDomain' => 'prefix_',
        ];
        $spec['MultiTest'] = [
            'dataMethod' => 'getFormats',
            'renderType' => 'Multi',
            'pos' => 1000,
            'multiFunction' => function ($data) {
                return [
                    'Multi Data' => $data,
                    'Multi Count' => count($data),
                ];
            }
        ];
        $spec['MultiEmptyArrayTest'] = [
            'dataMethod' => 'getFormats',
            'renderType' => 'Multi',
            'pos' => 2000,
            'multiFunction' => function () {
                return [];
            }
        ];
        $spec['MultiNullTest'] = [
            'dataMethod' => 'getFormats',
            'renderType' => 'Multi',
            'pos' => 2000,
            'multiFunction' => function () {
                return null;
            }
        ];
        $spec['MultiNullInArrayWithZeroTest'] = [
            'dataMethod' => 'getFormats',
            'renderType' => 'Multi',
            'pos' => 2000,
            'allowZero' => false,
            'multiFunction' => function () {
                return ['Null' => null, 'ZeroBlocked' => 0];
            }
        ];
        $spec['MultiNullInArrayWithZeroAllowedTest'] = [
            'dataMethod' => 'getFormats',
            'renderType' => 'Multi',
            'pos' => 2000,
            'allowZero' => true,
            'multiFunction' => function () {
                return ['Null' => null, 'ZeroAllowed' => 0];
            }
        ];

        $expected = [
            'Building' => 'prefix_0',
            'Published in' => '0',
            'Main Author' => 'Vico, Giambattista, 1668-1744.',
            'Other Authors' => 'Pandolfi, Claudia.',
            'Format' => 'Book',
            'Language' => 'ItalianLatin',
            'Published' => 'Centro di Studi Vichiani, 1992',
            'Edition' => 'Fictional edition.',
            'Multi Data' => 'Book',
            'Multi Count' => 1,
            'Series' => 'Vico, Giambattista, 1668-1744. Works. 1982 ;',
            'Subjects' => 'Naples (Kingdom) History Spanish rule, 1442-1707 Sources',
            'Online Access' => 'http://fictional.com/sample/url',
            'Tags' => 'Add Tag No Tags, Be the first to tag this record!',
            'ZeroAllowed' => 0,
        ];
        $driver = $this->getDriver();
        $results = $formatter->getData($driver, $spec);

        // Check for expected array keys
        $this->assertEquals(array_keys($expected), $this->getLabels($results));

        // Check for expected text (with markup stripped)
        foreach ($expected as $key => $value) {
            $this->assertEquals(
                $value,
                trim(
                    preg_replace(
                        '/\s+/', ' ',
                        strip_tags($this->findResult($key, $results)['value'])
                    )
                )
            );
        }

        // Check for exact markup in representative example:
        $this->assertEquals(
            'Italian<br />Latin', $this->findResult('Language', $results)['value']
        );

        // Check for context in Building:
        $this->assertEquals(
            ['foo' => 1], $this->findResult('Building', $results)['context']
        );
    }
}
