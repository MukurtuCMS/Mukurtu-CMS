<?php

namespace Drupal\Tests\term_merge\Functional;

use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Provides a base class for Term Merge functional tests.
 */
abstract class TermMergeTestBase extends BrowserTestBase {

  use TaxonomyTestTrait;
  use EntityReferenceFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'taxonomy',
    'term_merge',
    'term_merge_test_events',
  ];

  /**
   * The content type.
   *
   * @var \Drupal\node\Entity\NodeType
   */
  protected NodeType $contentType;

  /**
   * The vocabulary.
   *
   * @var \Drupal\taxonomy\Entity\Vocabulary
   */
  protected Vocabulary $vocabulary;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->contentType = $this->drupalCreateContentType();
    $this->vocabulary = $this->createVocabulary();
    $this->createEntityReferenceField('node', $this->contentType->id(), 'field_tags', 'Tags', 'taxonomy_term', 'default', [], -1);
  }

}
