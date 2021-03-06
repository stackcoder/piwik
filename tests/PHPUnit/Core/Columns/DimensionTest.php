<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Test\Columns
{
    // there is a test that requires the class to be defined in a plugin

    use Piwik\Columns\Dimension;
    use Piwik\Plugin\Segment;

    class DimensionTest extends Dimension
    {
        protected $columnName  = 'test_dimension';
        protected $columnType  = 'INTEGER (10) DEFAULT 0';

        public function set($param, $value)
        {
            $this->$param = $value;
        }

        protected function configureSegments()
        {
            $segment = new Segment();
            $segment->setSegment('exitPageUrl');
            $segment->setName('Actions_ColumnExitPageURL');
            $segment->setCategory('General_Visit');
            $this->addSegment($segment);

            // custom type and sqlSegment
            $segment = new Segment();
            $segment->setSegment('exitPageUrl');
            $segment->setSqlSegment('customValue');
            $segment->setType(Segment::TYPE_METRIC);
            $segment->setName('Actions_ColumnExitPageURL');
            $segment->setCategory('General_Visit');
            $this->addSegment($segment);
        }
    }
}

namespace Piwik\Tests\Core\Columns
{
    use Piwik\Columns\Dimension;
    use Piwik\Config;
    use Piwik\Plugin\Segment;
    use Piwik\Plugin\Manager;
    use Piwik\Plugins\Test\Columns\DimensionTest;
    use Piwik\Plugins\Test\FakeActionDimension;

    /**
     * @group Core
     */
    class Core_DimensionTest extends \PHPUnit_Framework_TestCase
    {
        /**
         * @var FakeActionDimension
         */
        private $dimension;

        public function setUp()
        {
            Manager::getInstance()->unloadPlugins();
            Manager::getInstance()->doNotLoadAlwaysActivatedPlugins();
            Config::getInstance()->clear();
            Config::getInstance()->init();

            $this->dimension = new DimensionTest();
        }

        public function tearDown()
        {
            Config::unsetInstance();
            Manager::unsetInstance();
            parent::tearDown();
        }

        public function test_hasImplementedEvent_shouldDetectWhetherAMethodWasOverwrittenInTheActualPluginClass()
        {
            $this->assertTrue($this->dimension->hasImplementedEvent('set'));
            $this->assertTrue($this->dimension->hasImplementedEvent('configureSegments'));

            $this->assertFalse($this->dimension->hasImplementedEvent('getSegments'));
        }

        public function test_getColumnName_shouldReturnTheNameOfTheColumn()
        {
            $this->assertSame('test_dimension', $this->dimension->getColumnName());
        }

        public function test_hasColumnType_shouldDetectWhetherAColumnTypeIsSet()
        {
            $this->assertTrue($this->dimension->hasColumnType());

            $this->dimension->set('columnType', '');
            $this->assertFalse($this->dimension->hasColumnType());
        }

        public function test_getName_ShouldNotReturnANameByDefault()
        {
            $this->assertSame('', $this->dimension->getName());
        }

        public function test_getAllDimensions_shouldReturnActionVisitAndConversionDimensions()
        {
            Manager::getInstance()->loadPlugins(array('Actions', 'Events', 'DevicesDetector', 'Goals'));

            $dimensions = Dimension::getAllDimensions();

            $this->assertGreaterThan(20, count($dimensions));

            $foundConversion = false;
            $foundVisit      = false;
            $foundAction     = false;

            foreach ($dimensions as $dimension) {
                if ($dimension instanceof \Piwik\Plugin\Dimension\ConversionDimension) {
                    $foundConversion = true;
                } else if ($dimension instanceof \Piwik\Plugin\Dimension\ActionDimension) {
                    $foundAction = true;
                } else if ($dimension instanceof \Piwik\Plugin\Dimension\VisitDimension) {
                    $foundVisit = true;
                } else {
                    $this->fail('Unexpected dimension class found');
                }

                $this->assertRegExp('/Piwik.Plugins.(Actions|Events|DevicesDetector|Goals).Columns/', get_class($dimension));
            }

            $this->assertTrue($foundConversion);
            $this->assertTrue($foundAction);
            $this->assertTrue($foundVisit);
        }

        public function test_getSegment_ShouldReturnConfiguredSegments()
        {
            $segments = $this->dimension->getSegments();

            $this->assertCount(2, $segments);
            $this->assertInstanceOf('\Piwik\Plugin\Segment', $segments[0]);
            $this->assertInstanceOf('\Piwik\Plugin\Segment', $segments[1]);
        }

        public function test_addSegment_ShouldPrefilSomeSegmentValuesIfNotDefinedYet()
        {
            $segments = $this->dimension->getSegments();

            $this->assertEquals(Segment::TYPE_DIMENSION, $segments[0]->getType());
        }

        public function test_addSegment_ShouldNotOverwritePreAssignedValues()
        {
            $segments = $this->dimension->getSegments();

            $this->assertEquals(Segment::TYPE_METRIC, $segments[1]->getType());
        }

        public function test_getId_ShouldCorrectlyGenerateIdFromDimensionsQualifiedClassName()
        {
            $this->assertEquals("Test.DimensionTest", $this->dimension->getId());
        }

        public function test_factory_ShouldCreateDimensionFromDimensionId()
        {
            Manager::getInstance()->loadPlugins(array('ExampleTracker'));

            $dimension = Dimension::factory("ExampleTracker.ExampleDimension");
            $this->assertInstanceOf("Piwik\\Plugins\\ExampleTracker\\Columns\\ExampleDimension", $dimension);
        }
   }
}