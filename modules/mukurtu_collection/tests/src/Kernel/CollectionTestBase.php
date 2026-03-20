<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_collection\Kernel;

use Drupal\Tests\mukurtu_core\Kernel\MukurtuKernelTestBase;
use Drupal\mukurtu_collection\Entity\Collection;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Base class for Mukurtu Collection kernel tests.
 */
abstract class CollectionTestBase extends MukurtuKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'content_moderation',
    'field',
    'file',
    'filter',
    'geofield',
    'image',
    'media',
    'node',
    'node_access_test',
    'og',
    'options',
    'path',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'workflows',
    'mukurtu_collection',
    'mukurtu_core',
    'mukurtu_drafts',
    'mukurtu_local_contexts',
    'mukurtu_protocol',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);

    // Create the collection bundle so hook_entity_bundle_info_alter assigns
    // the Collection bundle class.
    NodeType::create(['type' => 'collection', 'name' => 'Collection'])->save();

    // Create a generic content type for items inside collections.
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    // Vocabularies referenced by Collection bundle field definitions.
    Vocabulary::create(['vid' => 'keywords', 'name' => 'Keywords'])->save();
    Vocabulary::create(['vid' => 'location', 'name' => 'Location'])->save();
  }

  /**
   * {@inheritdoc}
   *
   * Add collection-specific CRUD permissions to the protocol steward OG role.
   */
  protected function getProtocolStewardPermissions(): array {
    return array_merge(parent::getProtocolStewardPermissions(), [
      'create collection content',
      'edit any collection content',
      'edit own collection content',
      'delete any collection content',
      'delete own collection content',
    ]);
  }

  /**
   * Build an unsaved Collection node with protocol set.
   *
   * @param string $title
   *   The collection title.
   *
   * @return \Drupal\mukurtu_collection\Entity\Collection
   */
  protected function buildCollection(string $title): Collection {
    /** @var \Drupal\mukurtu_collection\Entity\Collection $collection */
    $collection = Node::create([
      'type' => 'collection',
      'title' => $title,
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $collection->setSharingSetting('any');
    $collection->setProtocols([$this->protocol]);
    return $collection;
  }

  /**
   * Build an unsaved page node to use as a collection item.
   *
   * @param string $title
   *   The node title.
   *
   * @return \Drupal\node\Entity\Node
   */
  protected function buildItem(string $title): Node {
    return Node::create([
      'type' => 'page',
      'title' => $title,
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
  }

}
