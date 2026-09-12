<?php

declare(strict_types=1);

namespace Drupal\mukurtu_landing_page;

use Drupal\block_content\Entity\BlockContent;
use Drupal\node\Entity\Node;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\layout_builder\InlineBlockUsageInterface;
use Drupal\node\NodeInterface;

/**
 * Service for creating default landing pages.
 */
class DefaultLandingPage {

  use StringTranslationTrait;

  /**
   * State key used to track the UUIDs of the default landing page blocks.
   */
  private const STATE_KEY = 'mukurtu_landing_page.default_blocks';

  /**
   * The display config whose Layout Builder defaults hold the homepage layout.
   *
   * The section/component labels live here (not on the homepage node's own
   * override field) specifically so they are reachable by Configuration
   * Translation - see mukurtu_multilingual.config_translation.yml.
   */
  public const DISPLAY_ID = 'node.landing_page.default';

  /**
   * UUID of the hero component shipped in the display's default section.
   *
   * Public so mukurtu_landing_page_update_40201() can patch the same shipped
   * component on existing sites.
   */
  public const HERO_COMPONENT_UUID = 'bccc8859-099e-4a39-9abc-59fceae3a7d0';

  /**
   * UUID of the featured content component shipped in the default section.
   *
   * Public so mukurtu_landing_page_update_40201() can patch the same shipped
   * component on existing sites.
   */
  public const FEATURED_COMPONENT_UUID = 'ac4c7245-7c5e-4384-98bb-73dd2ff5d505';

  /**
   * Constructs a DefaultLandingPage object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\layout_builder\InlineBlockUsageInterface $inlineBlockUsage
   *   The inline block usage service.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected StateInterface $state,
    protected InlineBlockUsageInterface $inlineBlockUsage,
  ) {
  }

  /**
   * Loads a previously created default landing page block, or creates one.
   *
   * Blocks are tracked by UUID in state (keyed by $key) so that a later
   * call, such as re-creating the landing page after a migration, reuses
   * the original blocks instead of creating duplicates.
   *
   * @param string $key
   *   A stable identifier for this block within the default landing page.
   * @param string $bundle
   *   The block_content bundle expected for this block.
   * @param array $values
   *   Field values to use if the block needs to be created.
   * @param bool $reusable
   *   Whether the block should be reusable. Blocks placed as Layout Builder
   *   inline blocks (e.g. Featured Content) must be non-reusable; blocks
   *   referenced by the layout as reusable "Content block" plugins keep the
   *   default TRUE.
   *
   * @return \Drupal\block_content\Entity\BlockContent
   *   The existing or newly created block.
   */
  protected function getOrCreateBlock(string $key, string $bundle, array $values, bool $reusable = TRUE): BlockContent {
    $uuids = $this->state->get(self::STATE_KEY, []);
    if (!empty($uuids[$key])) {
      $existing = $this->entityTypeManager->getStorage('block_content')
        ->loadByProperties(['uuid' => $uuids[$key]]);
      $block = reset($existing);
      if ($block instanceof BlockContent && $block->bundle() === $bundle) {
        // Reconcile the reusable flag in case an earlier install or migration
        // created this block with the wrong value.
        if ($block->isReusable() !== $reusable) {
          $block->set('reusable', $reusable);
          $block->setNewRevision(TRUE);
          $block->save();
        }
        return $block;
      }
    }

    $values['reusable'] = $reusable;
    $block = BlockContent::create($values);
    $block->save();
    $uuids[$key] = $block->uuid();
    $this->state->set(self::STATE_KEY, $uuids);

    return $block;
  }

  /**
   * Creates the default landing page with blocks and layout.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The created landing page node, or NULL if creation failed.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   When something goes wrong with saving required entities.
   */
  public function createDefaultLandingPage(): ?NodeInterface {
    // Create block content for Layout Builder (no theme placement).
    // Reuse a previously created default block (e.g. from the original
    // install) when one exists, rather than creating a duplicate - this
    // matters when the landing page is re-created after a migration.
    // Hero Image block content.
    $hero_block_content = $this->getOrCreateBlock('hero', 'image_with_description', [
      'type' => 'image_with_description',
      'info' => $this->t('Welcome to Your Mukurtu CMS Site'),
      'body' => [
        'value' => '<p>To start using your Mukurtu site, create a community, cultural protocol, and category.</p>',
        'format' => 'basic_html',
      ],
      'region' => 'content',
      'weight' => 2,
      'theme' => 'mukurtu_v4',
      // Initialize the image field to avoid issues.
      'field_image' => [],
    ]);
    // Store UUID for Layout Builder reference
    $hero_block_uuid = $hero_block_content->uuid();

    // Vertical Image with Description block content.
    $this->getOrCreateBlock('vertical_hero', 'vertical_image_with_description', [
      'type' => 'vertical_image_with_description',
      'info' => $this->t('Welcome to Your Mukurtu CMS Site (Vertical)'),
      'body' => [
        'value' => '<p>To start using your Mukurtu site, create a community, cultural protocol, and category.</p>',
        'format' => 'basic_html',
      ],
      'region' => 'content',
      'weight' => 2,
      'theme' => 'mukurtu_v4',
      // Initialize the image field to avoid issues.
      'field_image' => [],
    ]);

    // Featured Content block content.
    $featured_block_content = $this->getOrCreateBlock('featured', 'featured_content', [
      'type' => 'featured_content',
      'info' => $this->t('Featured Content'),
      'body' => [
        'value' => '',
        'format' => 'basic_html',
      ],
      'region' => 'content',
      'weight' => 3,
      'theme' => 'mukurtu_v4',
      // Initialize the featured content field to avoid issues.
      'field_featured_content' => [],
    ], reusable: FALSE);

    // Full Image with Description block content.
    $this->getOrCreateBlock('full_image', 'full_image_with_description', [
      'type' => 'full_image_with_description',
      'info' => $this->t('Welcome to Your Mukurtu CMS Site (Full Background)'),
      'body' => [
        'value' => '<p>To start using your Mukurtu site, create a community, cultural protocol, and category.</p>',
        'format' => 'basic_html',
      ],
      'region' => 'content',
      'weight' => 2,
      'theme' => 'mukurtu_v4',
      // Initialize the image field to avoid issues.
      'field_image' => [],
      // Initialize the text color field to avoid issues.
      'field_text_color' => [],
    ]);

    // Language switcher block is now managed by config/install/block.block.mukurtu_v4_languageswitcher_1.yml

    // Footer block is now managed by config/install/block.block.mukurtu_footer_1.yml

    // Create default landing page and set it as homepage. Its Layout Builder
    // content is not stored on the node - see
    // updateDisplayDefaultBlockReferences() below.
    $homepage_node = Node::create([
      'type' => 'landing_page', // Use basic page temporarily to test access
      'title' => 'Mukurtu Homepage',
      'status' => TRUE, // Explicitly set as published
      'promote' => FALSE,
      'sticky' => FALSE,
      'uid' => 1,
    ]);
    $homepage_node->save();

    // The homepage's block headings ("Featured Content", "Browse by
    // Category", etc.) are shipped as this bundle's shared Layout Builder
    // defaults (core.entity_view_display.node.landing_page.default.yml),
    // not written onto the node's own override field, so they are reachable
    // by Configuration Translation. Only the block_content references below
    // are dynamic (created above); patch them onto the already-installed
    // display config.
    $this->updateDisplayDefaultBlockReferences($hero_block_uuid, $featured_block_content);

    // Record the inline block's usage against the homepage node. The layout
    // config already carries a block_revision_id, so Layout Builder's own
    // pre-save handler will not add this for us.
    $this->inlineBlockUsage->addUsage((int) $featured_block_content->id(), $homepage_node);

    // Set the homepage to the new landing page node (system.site.yml is not owned by Mukurtu).
    $this->configFactory
      ->getEditable('system.site')
      ->set('page.front', '/node/' . $homepage_node->id())
      ->save();

    return $homepage_node;
  }

  /**
   * Patches the display's shipped default section with real block references.
   *
   * The hero and featured components' labels/placement are shipped as static
   * config (core.entity_view_display.node.landing_page.default.yml), but
   * their block_content references can only be known once those entities
   * exist. This is idempotent: it always overwrites the same shipped
   * component entries, so calling createDefaultLandingPage() again (e.g.
   * after a migration) does not duplicate or drift the display's sections.
   *
   * @param string $hero_block_uuid
   *   UUID of the hero image_with_description block_content entity.
   * @param \Drupal\block_content\Entity\BlockContent $featured_block_content
   *   The non-reusable featured_content block_content entity.
   */
  protected function updateDisplayDefaultBlockReferences(string $hero_block_uuid, BlockContent $featured_block_content): void {
    /** @var \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay|null $display */
    $display = $this->entityTypeManager->getStorage('entity_view_display')->load(self::DISPLAY_ID);
    if (!$display || !$display->getSections()) {
      return;
    }

    $section = $display->getSection(0);
    $hero_component = $section->getComponent(self::HERO_COMPONENT_UUID);
    $featured_component = $section->getComponent(self::FEATURED_COMPONENT_UUID);

    $hero_config = $hero_component->get('configuration');
    $hero_config['id'] = 'block_content:' . $hero_block_uuid;
    $hero_component->setConfiguration($hero_config);

    $featured_config = $featured_component->get('configuration');
    $featured_config['block_id'] = (int) $featured_block_content->id();
    $featured_config['block_revision_id'] = (int) $featured_block_content->getRevisionId();
    $featured_component->setConfiguration($featured_config);

    $display->save();
  }

}
