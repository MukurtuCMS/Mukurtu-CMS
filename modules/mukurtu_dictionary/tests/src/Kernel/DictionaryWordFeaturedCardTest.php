<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Template\Attribute;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that dictionary word cards show the word's image (#2571).
 *
 * Related content and featured content render items as cards in the
 * featured view mode, through node--featured.html.twig.
 */
#[Group('mukurtu_dictionary')]
class DictionaryWordFeaturedCardTest extends DictionaryTestBase {

  /**
   * {@inheritdoc}
   *
   * The card template uses twig_tweak's add_suggestion filter.
   */
  protected static $modules = ['twig_tweak'];

  /**
   * The display settings the shipped config and the update hook both use.
   */
  private const COMPONENT = [
    'type' => 'entity_reference_entity_view',
    'label' => 'hidden',
    'settings' => [
      'view_mode' => 'teaser_featured',
      'link' => FALSE,
    ],
    'third_party_settings' => [],
    'weight' => 2,
    'region' => 'content',
  ];

  /**
   * Renders the card template with the given media fields shown.
   */
  private function renderCard(array $content): string {
    $theme_path = $this->container->get('extension.list.theme')->getPath('mukurtu_v4');
    $template = file_get_contents($this->root . '/' . $theme_path . '/templates/content/node--featured.html.twig');
    $build = [
      '#type' => 'inline_template',
      '#template' => $template,
      '#context' => [
        'attributes' => new Attribute(),
        'title_attributes' => new Attribute(),
        'node' => ['type' => ['entity' => ['label' => 'Dictionary Word']]],
        'label' => ['#theme' => 'image', '#uri' => 'public://label.png', '#alt' => 'aloha'],
        'url' => '/dictionary-word/aloha',
        // add_suggestion needs a themed element, so each field is an image
        // named after its marker.
        'content' => array_map(fn($marker) => [['#theme' => 'image', '#uri' => "public://$marker.png", '#alt' => '']], $content),
      ],
    ];
    return (string) $this->container->get('renderer')->renderInIsolation($build);
  }

  /**
   * The card picks one media field, in order of preference.
   */
  #[DataProvider('mediaProvider')]
  public function testCardMedia(array $content, ?string $expected): void {
    $media = $this->renderCard($content);
    // Only the expected field reaches the card.
    foreach ($content as $marker) {
      $marker === $expected
        ? $this->assertStringContainsString($marker, $media)
        : $this->assertStringNotContainsString($marker, $media);
    }
  }

  /**
   * Combinations of shown media fields, and which one the card should use.
   */
  public static function mediaProvider(): array {
    return [
      'image beats recording' => [
        ['field_representative_media' => 'IMAGE', 'field_recording' => 'RECORDING'],
        'IMAGE',
      ],
      'recording when there is no image' => [
        ['field_recording' => 'RECORDING'],
        'RECORDING',
      ],
      'custom thumbnail beats image' => [
        ['field_thumbnail' => 'THUMBNAIL', 'field_representative_media' => 'IMAGE'],
        'THUMBNAIL',
      ],
      'other bundles still fall back' => [
        ['field_collection_image' => 'COLLECTION'],
        'COLLECTION',
      ],
    ];
  }

  /**
   * The shipped featured display shows the word's representative media.
   */
  public function testShippedDisplay(): void {
    $path = $this->container->get('extension.list.module')->getPath('mukurtu_dictionary');
    $config = Yaml::decode(file_get_contents($path . '/config/install/core.entity_view_display.node.dictionary_word.featured.yml'));
    $this->assertSame(self::COMPONENT, $config['content']['field_representative_media']);
    $this->assertArrayNotHasKey('field_representative_media', $config['hidden']);
  }

  /**
   * Creates the featured display without the representative media field.
   */
  private function createDisplay(): EntityViewDisplay {
    EntityViewMode::create(['id' => 'node.featured', 'targetEntityType' => 'node', 'label' => 'Featured'])->save();
    $display = EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'dictionary_word',
      'mode' => 'featured',
      'status' => TRUE,
    ]);
    $display->removeComponent('field_representative_media')->save();
    return $display;
  }

  /**
   * The update shows the field on existing sites.
   */
  public function testUpdateAddsField(): void {
    $this->createDisplay();
    $this->container->get('module_handler')->loadInclude('mukurtu_dictionary', 'install');
    mukurtu_dictionary_update_40048();

    $component = EntityViewDisplay::load('node.dictionary_word.featured')->getComponent('field_representative_media');
    $this->assertSame(self::COMPONENT, $component);
  }

  /**
   * The update leaves a site's own placement alone.
   */
  public function testUpdateKeepsCustomPlacement(): void {
    $this->createDisplay()
      ->setComponent('field_representative_media', ['weight' => 9] + self::COMPONENT)
      ->save();
    $this->container->get('module_handler')->loadInclude('mukurtu_dictionary', 'install');
    mukurtu_dictionary_update_40048();

    $component = EntityViewDisplay::load('node.dictionary_word.featured')->getComponent('field_representative_media');
    $this->assertSame(9, $component['weight']);
  }

}
