<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_place\Kernel;

use Drupal\Tests\mukurtu_core\Kernel\MukurtuKernelTestBase;
use Drupal\mukurtu_place\Entity\Place;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Base class for Mukurtu Place kernel tests.
 */
abstract class PlaceTestBase extends MukurtuKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'comment',
    'content_moderation',
    'entity_browser',
    'entity_reference_revisions',
    'field',
    'field_group',
    'file',
    'filter',
    'geofield',
    'image',
    'layout_builder',
    'link',
    'media',
    'media_library',
    'menu_ui',
    'node',
    'node_access_test',
    'og',
    'options',
    'paragraphs',
    'path',
    'path_alias',
    'system',
    'tagify',
    'taxonomy',
    'text',
    'user',
    'views',
    'workflows',
    'mukurtu_core',
    'mukurtu_drafts',
    'mukurtu_local_contexts',
    'mukurtu_place',
    'mukurtu_protocol',
    'original_date',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('comment', 'comment_entity_statistics');
    $this->installSchema('layout_builder', ['inline_block_usage']);
    $this->installSchema('node', ['node_access']);

    $this->installEntitySchema('comment');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('path_alias');

    NodeType::create(['type' => 'place', 'name' => 'Place'])->save();

    // Vocabularies referenced by Place bundle field definitions.
    Vocabulary::create(['vid' => 'keywords', 'name' => 'Keywords'])->save();
    Vocabulary::create(['vid' => 'location', 'name' => 'Location'])->save();
    Vocabulary::create(['vid' => 'place_type', 'name' => 'Place Type'])->save();

    node_access_rebuild();
  }

  /**
   * {@inheritdoc}
   *
   * Add place-specific CRUD permissions to the protocol steward OG role.
   */
  protected function getProtocolStewardPermissions(): array {
    return array_merge(parent::getProtocolStewardPermissions(), [
      'create place content',
      'edit any place content',
      'edit own place content',
      'delete any place content',
      'delete own place content',
    ]);
  }

  /**
   * Build an unsaved Place node with protocol set.
   *
   * @param string $title
   *   The place title.
   *
   * @return \Drupal\mukurtu_place\Entity\Place
   */
  protected function buildPlace(string $title): Place {
    /** @var \Drupal\mukurtu_place\Entity\Place $place */
    $place = Node::create([
      'type' => 'place',
      'title' => $title,
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $place->setSharingSetting('any');
    $place->setProtocols([$this->protocol]);
    return $place;
  }

}
