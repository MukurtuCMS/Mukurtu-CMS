<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Entity Browser lists get the opaque drag styles and script.
 *
 * Without them, an item dragged to reorder a collection was drawn as a faint,
 * translucent image (#808). The script switches Sortable to its fallback
 * mode, and has to run after the Entity Browser script that creates the
 * Sortable instances, so it is appended to that library's own JS list.
 *
 * Runs the hook against entity_browser's real library definitions rather
 * than through library.discovery, because enabling mukurtu_core in a Kernel
 * test pulls in most of the profile (see ChartsLibraryHooksTest).
 *
 * @see mukurtu_core_library_info_alter()
 */
#[Group('mukurtu_core')]
class EntityBrowserSortableLibraryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * The entity_browser library definitions, after the alter hook ran.
   */
  private array $libraries;

  /**
   * The web path of mukurtu_core, as the hook builds it.
   */
  private string $corePath;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_list = \Drupal::service('extension.list.module');
    require_once \Drupal::root() . '/' . $module_list->getPath('mukurtu_core') . '/mukurtu_core.module';

    $this->corePath = '/' . $module_list->getPath('mukurtu_core');
    $this->libraries = Yaml::decode(file_get_contents(\Drupal::root() . '/' . $module_list->getPath('entity_browser') . '/entity_browser.libraries.yml'));
    mukurtu_core_library_info_alter($this->libraries, 'entity_browser');
  }

  /**
   * The script is the last one in entity_reference, after Entity Browser's.
   */
  public function testScriptLoadsAfterEntityBrowser(): void {
    $scripts = array_keys($this->libraries['entity_reference']['js']);
    $this->assertSame($this->corePath . '/js/entity-browser-sortable.js', end($scripts));
    $this->assertContains('js/entity_browser.entity_reference.js', $scripts);
    $this->assertFileExists(\Drupal::root() . $this->corePath . '/js/entity-browser-sortable.js');
  }

  /**
   * The styles are added to every entity list.
   */
  public function testStylesAddedToEntityList(): void {
    $this->assertArrayHasKey($this->corePath . '/css/entity-browser-sortable.css', $this->libraries['entity_list']['css']['component']);
    $this->assertFileExists(\Drupal::root() . $this->corePath . '/css/entity-browser-sortable.css');
  }

  /**
   * Other extensions' libraries are left alone.
   */
  public function testOtherExtensionsUntouched(): void {
    $libraries = $original = ['entity_reference' => ['js' => ['a.js' => []]]];
    mukurtu_core_library_info_alter($libraries, 'some_other_module');
    $this->assertSame($original, $libraries);
  }

}
