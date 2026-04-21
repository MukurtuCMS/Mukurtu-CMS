<?php

namespace Drupal\Tests\facets_demo\Functional;

use Drupal\Tests\facets\Functional\FacetsTestBase;
use Drupal\taxonomy\Entity\Term;

/**
 * Tests the overall functionality of the Facets admin UI.
 *
 * @group facets
 */
class FacetsDemoTest extends FacetsTestBase {

  protected $profile = 'standard';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'datetime',
    'menu_ui',
    'facets_demo',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Unpublish one of the terms.
    $term = Term::load(1);
    $term->setUnpublished();
    $term->save();
    
    // Index all content.
    $this->indexItems($this->indexId);

    // $this->assertEquals($this->indexItems($this->indexId), 5, '5 items were indexed.');
  }

  /**
   * Tests the demo page.
   */
  public function testDemoPage() {
    $this->drupalGet('movies');
  }

}
