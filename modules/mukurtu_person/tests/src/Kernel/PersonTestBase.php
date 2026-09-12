<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_person\Kernel;

use Drupal\Tests\mukurtu_core\Kernel\MukurtuKernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mukurtu_person\Entity\Person;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Base class for Mukurtu Person kernel tests.
 */
abstract class PersonTestBase extends MukurtuKernelTestBase {

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
    'mukurtu_person',
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

    NodeType::create(['type' => 'person', 'name' => 'Person'])->save();

    // Vocabularies referenced by Person bundle field definitions.
    Vocabulary::create(['vid' => 'keywords', 'name' => 'Keywords'])->save();
    Vocabulary::create(['vid' => 'location', 'name' => 'Location'])->save();
    Vocabulary::create(['vid' => 'people', 'name' => 'People'])->save();

    // field_coverage is a config-based geofield, not a base field, so it
    // must be created explicitly rather than picked up automatically.
    if (!FieldStorageConfig::loadByName('node', 'field_coverage')) {
      FieldStorageConfig::create([
        'field_name' => 'field_coverage',
        'entity_type' => 'node',
        'type' => 'geofield',
        'cardinality' => 1,
      ])->save();
    }
    FieldConfig::create([
      'field_storage' => FieldStorageConfig::loadByName('node', 'field_coverage'),
      'bundle' => 'person',
      'label' => 'Map Points',
    ])->save();

    node_access_rebuild();
  }

  /**
   * {@inheritdoc}
   *
   * Add person-specific CRUD permissions to the protocol steward OG role.
   */
  protected function getProtocolStewardPermissions(): array {
    return array_merge(parent::getProtocolStewardPermissions(), [
      'create person content',
      'edit any person content',
      'edit own person content',
      'delete any person content',
      'delete own person content',
    ]);
  }

  /**
   * Build an unsaved Person node with protocol set.
   *
   * @param string $title
   *   The person title.
   *
   * @return \Drupal\mukurtu_person\Entity\Person
   */
  protected function buildPerson(string $title): Person {
    /** @var \Drupal\mukurtu_person\Entity\Person $person */
    $person = Node::create([
      'type' => 'person',
      'title' => $title,
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $person->setSharingSetting('any');
    $person->setProtocols([$this->protocol]);
    return $person;
  }

}
