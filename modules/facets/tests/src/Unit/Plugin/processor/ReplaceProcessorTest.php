<?php

namespace Drupal\Tests\facets\Unit\Plugin\processor;

use Drupal\Tests\UnitTestCase;
use Drupal\facets\Entity\Facet;
use Drupal\facets\Result\Result;
use Drupal\facets\Plugin\facets\processor\ReplaceProcessor;

/**
 * Unit test for replacement processor.
 *
 * @group facets
 */
class ReplaceProcessorTest extends UnitTestCase {

  /**
   * The processor to be tested.
   *
   * @var \Drupal\facets\Processor\PostQueryProcessorInterface
   */
  protected $processor;

  /**
   * The facet.
   *
   * @var \Drupal\facets\Entity\Facet
   */
  protected $facet;

  /**
   * An array containing the results before the processor has run.
   *
   * @var \Drupal\facets\Result\Result[]
   */
  protected $results;

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->facet = new Facet([], 'facets_facet');
    $this->results = [
      new Result($this->facet, 'en', 'Foo', 10),
      new Result($this->facet, 'fr', 'Bar', 5),
    ];
    $this->facet->setResults($this->results);
    $this->processor = new ReplaceProcessor([], 'replace', []);
  }

  /**
   * Tests default configuration.
   */
  public function testDefaultConfiguration() {
    $config = $this->processor->defaultConfiguration();
    $this->assertEquals(['replacements' => ''], $config);
  }

  /**
   * Tests no replacement.
   */
  public function testNoneReplaced() {
    $this->processor->setConfiguration([
      'replacements' => '',
    ]);
    $this->processor->postQuery($this->facet);

    // The processor should not affect the original facet values.
    $this->assertEquals(2, count($this->results));
    $this->assertEquals('en', $this->results[0]->getRawValue());
    $this->assertEquals('Foo', $this->results[0]->getDisplayValue());
    $this->assertEquals('fr', $this->results[1]->getRawValue());
    $this->assertEquals('Bar', $this->results[1]->getDisplayValue());
  }

  /**
   * Tests replace all values.
   */
  public function testAllReplaced() {
    $this->processor->setConfiguration([
      'replacements' => <<<EOT
en|English
fr|French
EOT,
    ]);
    $this->processor->postQuery($this->facet);

    // All display values should be replaced.
    $this->assertEquals(2, count($this->results));
    $this->assertEquals('en', $this->results[0]->getRawValue());
    $this->assertEquals('English', $this->results[0]->getDisplayValue());
    $this->assertEquals('fr', $this->results[1]->getRawValue());
    $this->assertEquals('French', $this->results[1]->getDisplayValue());
  }

  /**
   * Tests replace some (but not all) values.
   */
  public function testSomeReplaced() {
    $this->processor->setConfiguration([
      'replacements' => 'en|English',
    ]);
    $this->processor->postQuery($this->facet);

    // Only values listed in replacement list should be replaced.
    $this->assertEquals(2, count($this->results));
    $this->assertEquals('en', $this->results[0]->getRawValue());
    $this->assertEquals('English', $this->results[0]->getDisplayValue());
    $this->assertEquals('fr', $this->results[1]->getRawValue());
    $this->assertEquals('Bar', $this->results[1]->getDisplayValue());
  }

  /**
   * Tests edge case - a line that contains multiple pipes.
   */
  public function testMultiplePipes() {
    $this->processor->setConfiguration([
      'replacements' => <<<EOT
en|English
fr|French|Francais
EOT,
    ]);
    $this->processor->postQuery($this->facet);

    // The valid replacement (en|English) should work.
    // The invalid one (fr|French|Francais) should have no effect.
    $this->assertEquals(2, count($this->results));
    $this->assertEquals('en', $this->results[0]->getRawValue());
    $this->assertEquals('English', $this->results[0]->getDisplayValue());
    $this->assertEquals('fr', $this->results[1]->getRawValue());
    $this->assertEquals('Bar', $this->results[1]->getDisplayValue());
  }

  /**
   * Tests edge case - a line that contains only a pipe.
   */
  public function testOnlyPipe() {
    $this->processor->setConfiguration([
      'replacements' => <<<EOT
en|English
fr|French
|
EOT,
    ]);
    $this->processor->postQuery($this->facet);

    // The broken line should have no effect ; replacements should still work.
    $this->assertEquals(2, count($this->results));
    $this->assertEquals('en', $this->results[0]->getRawValue());
    $this->assertEquals('English', $this->results[0]->getDisplayValue());
    $this->assertEquals('fr', $this->results[1]->getRawValue());
    $this->assertEquals('French', $this->results[1]->getDisplayValue());
  }

  /**
   * Tests edge case - empty display value in replacement.
   */
  public function testEmptyDisplayValue() {
    $this->processor->setConfiguration([
      'replacements' => <<<EOT
en|English
fr|
EOT,
    ]);
    $this->processor->postQuery($this->facet);

    // Empty display values should be tolerated.
    $this->assertEquals(2, count($this->results));
    $this->assertEquals('en', $this->results[0]->getRawValue());
    $this->assertEquals('English', $this->results[0]->getDisplayValue());
    $this->assertEquals('fr', $this->results[1]->getRawValue());
    $this->assertEquals('', $this->results[1]->getDisplayValue());
  }

  /**
   * Tests edge case - spaces before/after a replacement.
   */
  public function testSpaces() {
    $this->processor->setConfiguration([
      'replacements' => <<<EOT
en |English
 fr |  French
EOT,
    ]);
    $this->processor->postQuery($this->facet);

    // Spaces should be trimmed ; replacements should still work.
    $this->assertEquals(2, count($this->results));
    $this->assertEquals('en', $this->results[0]->getRawValue());
    $this->assertEquals('English', $this->results[0]->getDisplayValue());
    $this->assertEquals('fr', $this->results[1]->getRawValue());
    $this->assertEquals('French', $this->results[1]->getDisplayValue());
  }

}
