<?php

declare(strict_types=1);

namespace Drupal\mukurtu_gin_custom;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Url;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Builds the front-end LB block edit URLs for a node.
 *
 * The map is keyed by component UUID, matching the data-layout-block-uuid
 * attribute LbFrontendEditSubscriber stamps on each rendered block.
 */
class LbFrontendEditUrls {

  public function __construct(
    protected ?SectionStorageManagerInterface $sectionStorageManager,
    protected EntityRepositoryInterface $entityRepository,
  ) {}

  /**
   * Builds a component UUID to edit URL map for every non-field block.
   *
   * A node with no layout override of its own (such as the fresh-install
   * homepage, whose layout deliberately lives in the display defaults so its
   * headings stay config-translatable) renders the defaults' sections, so the
   * map is built from those. LbFrontendEditSeedSubscriber copies the defaults
   * into the override when one of these URLs is opened.
   *
   * - Reusable blocks (block_content:*): link to the entity edit form so all
   *   content fields are shown, not just the LB wrapper settings.
   * - All other non-field blocks: link to the LB configure-block form.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node being viewed.
   * @param string $return_url
   *   The URL to return to after editing.
   *
   * @return array<string, string>
   *   Edit URLs keyed by component UUID.
   */
  public function build(NodeInterface $node, string $return_url): array {
    if (!$this->sectionStorageManager) {
      return [];
    }
    $section_storage = $this->sectionStorageManager->load('overrides', [
      'entity' => EntityContext::fromEntity($node),
      'view_mode' => new Context(new ContextDefinition('string'), 'full'),
    ]);
    if (!$section_storage instanceof OverridesSectionStorageInterface) {
      return [];
    }
    $sections = $section_storage->isOverridden()
      ? $section_storage->getSections()
      : $section_storage->getDefaultSectionStorage()->getSections();

    $storage_id = $node->getEntityTypeId() . '.' . $node->id();
    $edit_urls = [];
    foreach ($sections as $delta => $section) {
      foreach ($section->getComponents() as $component) {
        $plugin_id = $component->getPlugin()->getPluginId();
        if (str_starts_with($plugin_id, 'field_block:') || str_starts_with($plugin_id, 'extra_field_block:')) {
          continue;
        }
        if (str_starts_with($plugin_id, 'block_content:')) {
          // Reusable block: edit the block_content entity directly.
          $block_uuid = substr($plugin_id, strlen('block_content:'));
          $block_content = $this->entityRepository->loadEntityByUuid('block_content', $block_uuid);
          if ($block_content && $block_content->access('update')) {
            $edit_urls[$component->getUuid()] = $block_content->toUrl('edit-form', [
              'query' => ['destination' => $return_url],
            ])->toString();
          }
        }
        else {
          // Inline block or other plugin: use the LB configure-block form.
          $edit_urls[$component->getUuid()] = Url::fromRoute('layout_builder.update_block', [
            'section_storage_type' => 'overrides',
            'section_storage' => $storage_id,
            'delta' => $delta,
            'region' => $component->getRegion(),
            'uuid' => $component->getUuid(),
          ], ['query' => ['frontend_edit' => '1', 'frontend_return' => $return_url]])->toString();
        }
      }
    }
    return $edit_urls;
  }

}
