<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Entity Browser lists get the reordering styles and scripts.
 *
 * Without them, an item dragged to reorder a collection was drawn as a faint,
 * translucent image (#808), and there was no way to reorder without dragging
 * (#2498). Both scripts have to run after the Entity Browser script that
 * creates the Sortable instances and the reorder function, so they are
 * appended to that library's own JS list.
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
   * The scripts load after Entity Browser's, the drag mode first.
   */
  public function testScriptsLoadAfterEntityBrowser(): void {
    $scripts = array_keys($this->libraries['entity_reference']['js']);
    $this->assertSame([
      'js/entity_browser.entity_reference.js',
      $this->corePath . '/js/entity-browser-sortable.js',
      $this->corePath . '/js/entity-browser-reorder.js',
    ], array_slice($scripts, -3));
    $this->assertFileExists(\Drupal::root() . $this->corePath . '/js/entity-browser-sortable.js');
    $this->assertFileExists(\Drupal::root() . $this->corePath . '/js/entity-browser-reorder.js');
  }

  /**
   * The reorder buttons get their styles and what their script needs.
   */
  public function testReorderButtonsHaveStylesAndDependencies(): void {
    $library = $this->libraries['entity_reference'];
    $this->assertArrayHasKey($this->corePath . '/css/entity-browser-reorder.css', $library['css']['component']);
    $this->assertFileExists(\Drupal::root() . $this->corePath . '/css/entity-browser-reorder.css');
    $this->assertContains('core/drupal.announce', $library['dependencies']);
    $this->assertContains('core/once', $library['dependencies']);
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
