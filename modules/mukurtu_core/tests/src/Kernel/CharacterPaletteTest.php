<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\CharacterPalette;
use Drupal\mukurtu_core\Form\CharacterPaletteSettingsForm;
use Drupal\mukurtu_core\Hook\CharacterPaletteHooks;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests which inputs get the character palette, and when (#2560).
 *
 * The palette's behavior in the browser is covered by
 * tests/playwright/tests/character-palette.offline.spec.ts.
 */
#[Group('mukurtu_core')]
class CharacterPaletteTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'path_alias',
    'path',
    'language',
    'taxonomy',
    'geofield',
    'leaflet',
    'node',
    'mukurtu_core',
  ];

  /**
   * A language with characters, as the settings form saves it.
   */
  protected const GROUP = [
    'label' => 'Tlingit',
    'characters' => [['character' => 'ḵ', 'name' => 'k underbar']],
  ];

  /**
   * Saves the palette's settings.
   */
  protected function setPalette(bool $enabled, array $groups): void {
    $this->config(CharacterPaletteSettingsForm::SETTINGS)
      ->set('enabled', $enabled)
      ->set('groups', $groups)
      ->save();
  }

  /**
   * Runs an element through the palette's #process callback.
   */
  protected function process(array $element): array {
    $form_state = new FormState();
    $complete_form = [];
    return CharacterPalette::processElement($element, $form_state, $complete_form);
  }

  /**
   * Tests that text fields, text areas and search boxes get the callback.
   */
  public function testElementTypes(): void {
    $element_info = $this->container->get('plugin.manager.element_info');
    foreach (CharacterPalette::ELEMENT_TYPES as $type) {
      $this->assertContains([CharacterPalette::class, 'processElement'], $element_info->getInfo($type)['#process'], "$type gets the palette.");
    }
    foreach (['email', 'password', 'url', 'number', 'tel'] as $type) {
      $this->assertNotContains([CharacterPalette::class, 'processElement'], $element_info->getInfo($type)['#process'] ?? [], "$type does not.");
    }
  }

  /**
   * Tests that an input is marked only while the palette is on and stocked.
   */
  public function testMarkedOnlyWhenActive(): void {
    $element = ['#type' => 'textfield', '#title' => 'Term'];

    $this->setPalette(FALSE, [self::GROUP]);
    $this->assertArrayNotHasKey(CharacterPalette::ATTRIBUTE, $this->process($element)['#attributes'] ?? [], 'Off: not marked.');

    $this->setPalette(TRUE, []);
    $this->assertArrayNotHasKey(CharacterPalette::ATTRIBUTE, $this->process($element)['#attributes'] ?? [], 'No characters: not marked.');

    $this->setPalette(TRUE, [self::GROUP]);
    $processed = $this->process($element);
    $this->assertSame('Term', $processed['#attributes'][CharacterPalette::ATTRIBUTE]);
    $this->assertContains('mukurtu_core/character_palette', $processed['#attached']['library']);
    $this->assertContains('config:' . CharacterPaletteSettingsForm::SETTINGS, $processed['#cache']['tags'], 'Turning the palette on or off clears cached forms.');
  }

  /**
   * Tests the field name the button is labeled with.
   */
  public function testLabel(): void {
    $this->setPalette(TRUE, [self::GROUP]);

    $cases = [
      'title, tags and entities removed' => [['#title' => '<em>Rights &amp; usage</em>'], 'Rights & usage'],
      'placeholder when no title' => [['#placeholder' => 'Search'], 'Search'],
      'aria-label when nothing else' => [['#attributes' => ['aria-label' => 'Keywords']], 'Keywords'],
      'empty when nothing at all' => [[], ''],
    ];
    foreach ($cases as $case => [$element, $expected]) {
      $processed = $this->process($element + ['#type' => 'textfield']);
      $this->assertSame($expected, $processed['#attributes'][CharacterPalette::ATTRIBUTE], $case);
    }
  }

  /**
   * Tests that an element can opt out, as the map widget's GeoJSON does.
   */
  public function testOptOut(): void {
    $this->setPalette(TRUE, [self::GROUP]);
    $processed = $this->process(['#type' => 'textarea', '#title' => 'Map Points', '#character_palette' => FALSE]);
    $this->assertArrayNotHasKey(CharacterPalette::ATTRIBUTE, $processed['#attributes'] ?? []);
  }

  /**
   * Tests that languages with no characters are left out.
   */
  public function testGroupsSkipEmptyLanguages(): void {
    $this->setPalette(TRUE, [['label' => 'Empty', 'characters' => []], self::GROUP]);
    $this->assertSame([self::GROUP], CharacterPalette::groups());
  }

  /**
   * Tests that every page gets the characters while the palette is on.
   */
  public function testPageAttachments(): void {
    $hooks = new CharacterPaletteHooks();

    $this->setPalette(FALSE, [self::GROUP]);
    $attachments = [];
    $hooks->pageAttachments($attachments);
    $this->assertSame([], $attachments['#attached']['library'] ?? [], 'Off: no script.');
    $this->assertArrayNotHasKey('mukurtuCharacterPalette', $attachments['#attached']['drupalSettings'] ?? []);
    $this->assertContains('config:' . CharacterPaletteSettingsForm::SETTINGS, $attachments['#cache']['tags']);

    $this->setPalette(TRUE, [self::GROUP]);
    $attachments = [];
    $hooks->pageAttachments($attachments);
    $this->assertContains('mukurtu_core/character_palette', $attachments['#attached']['library']);
    $this->assertSame([self::GROUP], $attachments['#attached']['drupalSettings']['mukurtuCharacterPalette']['groups']);
  }

}
