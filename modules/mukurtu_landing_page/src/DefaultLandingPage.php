<?php

declare(strict_types=1);

namespace Drupal\mukurtu_landing_page;

use Drupal\block_content\Entity\BlockContent;
use Drupal\node\Entity\Node;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\Component\Uuid\Php;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
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
   * Constructs a DefaultLandingPage object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected StateInterface $state,
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
   *
   * @return \Drupal\block_content\Entity\BlockContent
   *   The existing or newly created block.
   */
  protected function getOrCreateBlock(string $key, string $bundle, array $values): BlockContent {
    $uuids = $this->state->get(self::STATE_KEY, []);
    if (!empty($uuids[$key])) {
      $existing = $this->entityTypeManager->getStorage('block_content')
        ->loadByProperties(['uuid' => $uuids[$key]]);
      $block = reset($existing);
      if ($block instanceof BlockContent && $block->bundle() === $bundle) {
        return $block;
      }
    }

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
    ]);
    // Store UUID for Layout Builder reference
    $featured_block_uuid = $featured_block_content->uuid();

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

    // Create default landing page and set it as homepage.
    $homepage_node = Node::create([
      'type' => 'landing_page', // Use basic page temporarily to test access
      'title' => 'Mukurtu Homepage',
      'status' => TRUE, // Explicitly set as published
      'promote' => FALSE,
      'sticky' => FALSE,
      'uid' => 1,
    ]);
    $homepage_node->save();

    // Add existing blocks to Layout Builder.
    $uuid_generator = new Php();

    // Create section components for the existing blocks
    $hero_component = new SectionComponent(
      $uuid_generator->generate(), // UUID for this component
      'content', // Region name (layout_onecol has 'content' region)
      [
        'id' => 'block_content:' . $hero_block_uuid, // Reference to the block content
        'label' => 'Welcome to Your Mukurtu CMS Site',
        'label_display' => 1, // Display the title
        'provider' => 'block_content',
      ]
    );
    $hero_component->setWeight(0); // First block

    $featured_component = new SectionComponent(
      $uuid_generator->generate(),
      'content',
      [
        'id' => 'block_content:' . $featured_block_uuid,
        'label' => 'Featured Content',
        'label_display' => 1,
        'provider' => 'block_content',
      ]
    );
    $featured_component->setWeight(1); // Second block

    $categories_component = new SectionComponent(
      $uuid_generator->generate(),
      'content',
      [
        'id' => 'views_block:mukurtu_categories-browse_by_category_block',
        'label' => 'Browse by Category',
        'label_display' => 1,
        'provider' => 'views',
        'views_label' => '',
        'items_per_page' => 'none',
      ]
    );
    $categories_component->setWeight(2); // Third block

    $community_component = new SectionComponent(
      $uuid_generator->generate(),
      'content',
      [
        'id' => 'mukurtu_browse_by_community',
        'label' => 'Browse by Community',
        'label_display' => 1,
        'provider' => 'mukurtu_protocol',
      ]
    );
    $community_component->setWeight(3); // Fourth block

    $map_component = new SectionComponent(
      $uuid_generator->generate(),
      'content',
      [
        'id' => 'views_block:mukurtu_browse_by_map-map_block',
        'label' => 'Browse by Map',
        'label_display' => 1,
        'provider' => 'views',
        'views_label' => '',
        'items_per_page' => 'none',
      ]
    );
    $map_component->setWeight(4); // Fifth block

    // Create a section using single column layout
    $section = new Section(
      'layout_onecol', // Layout plugin ID
      [], // Layout settings (empty for default)
      [$hero_component, $featured_component, $categories_component, $community_component, $map_component] // Components array
    );

    // Set the layout on the node
    $homepage_node->set('layout_builder__layout', [$section]);
    $homepage_node->save();

    // Set the homepage to the new landing page node (system.site.yml is not owned by Mukurtu).
    $this->configFactory
      ->getEditable('system.site')
      ->set('page.front', '/node/' . $homepage_node->id())
      ->save();

    return $homepage_node;
  }

}
