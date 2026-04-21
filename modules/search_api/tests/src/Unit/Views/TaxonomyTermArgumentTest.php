<?php

namespace Drupal\Tests\search_api\Unit\Views;

use Drupal\search_api\Plugin\views\argument\SearchApiTerm;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\UnitTestCase;

/**
 * Tests whether the SearchApiTerm argument plugin works correctly.
 *
 * @group search_api
 *
 * @coversDefaultClass \Drupal\search_api\Plugin\views\argument\SearchApiTerm
 */
class TaxonomyTermArgumentTest extends UnitTestCase {

  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->setupContainer();
  }

  /**
   * Tests that null is returned if no argument has been set for any reason.
   *
   * @covers ::title
   */
  public function testReturnsNullIfArgumentNotSet() {
    $plugin = $this->getSubjectUnderTest();

    $this->assertNull($plugin->title());
  }

  /**
   * Tests that the set argument is returned when no value is provided.
   *
   * @covers ::title
   */
  public function testReturnsArgumentIfSet() {
    $plugin = $this->getSubjectUnderTest('argument');

    $plugin->value = [];
    $this->assertEquals('argument', $plugin->title());
  }

  /**
   * Tests that the set argument is returned when non existing ids are provided.
   *
   * @covers ::title
   */
  public function testReturnsArgumentIfInvalidTermIdIsPassed() {
    $plugin = $this->getSubjectUnderTest('argument');

    $prophecy = $this->prophesize(Term::class);
    $prophecy->label()->willReturn('First');
    $prophecy->id()->willReturn(1);
    $term = $prophecy->reveal();

    $non_existing_term_id = $term->id() + 1;
    $this->termStorage->expects($this->any())
      ->method('load')
      ->with($non_existing_term_id)
      ->willReturn(NULL);

    $plugin->value = [$non_existing_term_id];
    $this->assertEquals('argument', $plugin->title());
  }

  /**
   * Tests that the term label is returned if an existing id is provided.
   *
   * @covers ::title
   */
  public function testReturnsTermNameIfValidTermIdIsPassed() {
    $plugin = $this->getSubjectUnderTest('argument');

    $prophecy = $this->prophesize(Term::class);
    $prophecy->label()->willReturn('First');
    $prophecy->id()->willReturn(1);
    $term = $prophecy->reveal();
    $this->termStorage->expects($this->any())
      ->method('load')
      ->with($term->id())
      ->willReturn($term);
    $this->entityRepository->expects($this->any())
      ->method('getTranslationFromContext')
      ->with($term)
      ->willReturn($term);

    $plugin->value = [$term->id()];
    $this->assertEquals($term->label(), $plugin->title());
  }

  /**
   * Tests that a comma separated list of term labels is returned.
   *
   * @covers ::title
   */
  public function testReturnsCommaSeparatedNamesIfValidTermIdsArePassed() {
    $plugin = $this->getSubjectUnderTest('argument');

    $prophecy = $this->prophesize(Term::class);
    $prophecy->label()->willReturn('First');
    $prophecy->id()->willReturn(1);
    $term1 = $prophecy->reveal();
    $prophecy = $this->prophesize(Term::class);
    $prophecy->label()->willReturn('Second');
    $prophecy->id()->willReturn(2);
    $term2 = $prophecy->reveal();
    $this->termStorage->expects($this->exactly(2))
      ->method('load')
      ->willReturnMap([
        [$term1->id(), $term1],
        [$term2->id(), $term2],
      ]);
    $this->entityRepository->expects($this->exactly(2))
      ->method('getTranslationFromContext')
      ->willReturnMap([
        [$term1, NULL, [], $term1],
        [$term2, NULL, [], $term2],
      ]);

    $plugin->value = [$term1->id(), $term2->id()];

    $this->assertEquals("{$term1->label()}, {$term2->label()}", $plugin->title());
  }

  /**
   * Creates the plugin to test.
   *
   * @param string|null $argument
   *   The argument to set on the plugin.
   *
   * @return \Drupal\search_api\Plugin\views\argument\SearchApiTerm
   *   The subject under test.
   */
  protected function getSubjectUnderTest($argument = NULL) {
    $plugin = new SearchApiTerm([], 'search_api_term', []);
    if ($argument !== NULL) {
      $plugin->argument_validated = TRUE;
      $plugin->setArgument($argument);
    }
    return $plugin;
  }

}
