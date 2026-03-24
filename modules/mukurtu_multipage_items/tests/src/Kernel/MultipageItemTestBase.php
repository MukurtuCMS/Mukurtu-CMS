<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multipage_items\Kernel;

use Drupal\Tests\mukurtu_collection\Kernel\CollectionTestBase;
use Drupal\mukurtu_multipage_items\Entity\MultipageItem;

/**
 * Base class for Mukurtu Multipage Item kernel tests.
 */
abstract class MultipageItemTestBase extends CollectionTestBase {

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
    'mukurtu_multipage_items',
    'mukurtu_protocol',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('multipage_item');

    // Provide the bundle configuration used by MultipageItemManager::isEnabledBundleType().
    $this->config('mukurtu_multipage_items.settings')
      ->set('bundles_config', [
        'digital_heritage' => TRUE,
        'page' => FALSE,
      ])
      ->save();
  }

  /**
   * Build an unsaved MultipageItem entity.
   *
   * @param string $title
   *   The multipage item title.
   *
   * @return \Drupal\mukurtu_multipage_items\Entity\MultipageItem
   */
  protected function buildMultipageItem(string $title): MultipageItem {
    /** @var \Drupal\mukurtu_multipage_items\Entity\MultipageItem $mpi */
    $mpi = MultipageItem::create([
      'title' => $title,
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    return $mpi;
  }

}
