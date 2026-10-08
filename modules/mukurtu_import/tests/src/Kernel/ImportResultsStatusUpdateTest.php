<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\Core\Serialization\Yaml;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_import_update_40403() on views shaped like existing sites'.
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2309
 */
#[Group('mukurtu_import')]
class ImportResultsStatusUpdateTest extends MukurtuImportTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['history'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('history', ['history']);

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_import');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_import.install';

    // Recreate each view as it shipped before 4.0.4, from the current YAML.
    foreach (['content', 'media', 'taxonomy_terms'] as $name) {
      $values = Yaml::decode(file_get_contents(\Drupal::root() . '/' . $module_path . "/config/install/views.view.mukurtu_import_results_$name.yml"));
      unset($values['langcode'], $values['status'], $values['dependencies']);
      $options = &$values['display']['default']['display_options'];
      $style = &$options['style']['options'];
      match ($name) {
        // The History "unread" marker, labeled "New".
        'content' => $this->replaceStatusField($options, $style, 'timestamp', [
          'id' => 'timestamp',
          'table' => 'history',
          'field' => 'timestamp',
          'plugin_id' => 'history_user_timestamp',
          'label' => 'New',
        ]),
        // The former taxonomy-only status field.
        'taxonomy_terms' => $this->replaceStatusField($options, $style, 'mukurtu_import_term_status', [
          'id' => 'mukurtu_import_term_status',
          'table' => 'taxonomy_term_field_data',
          'field' => 'mukurtu_import_term_status',
          'plugin_id' => 'mukurtu_import_term_status',
          'label' => 'Status',
        ]),
        // No status column at all.
        'media' => $this->replaceStatusField($options, $style, NULL, []),
      };
      unset($options, $style);
      $this->entityTypeManager->getStorage('view')->create($values)->save();
    }
  }

  /**
   * The hook puts the Status field in place of the old column, or after the
   * first column when there was none.
   */
  public function testAddsStatusColumn(): void {
    $this->assertNotNull(mukurtu_import_update_40403());

    $content = $this->displayOptions('mukurtu_import_results_content');
    $this->assertSame(['title', 'mukurtu_import_status', 'status', 'type', 'changed'], array_keys($content['fields']));
    $this->assertSame('mukurtu_import_status', $content['fields']['mukurtu_import_status']['plugin_id']);
    $this->assertSame('Status', $content['fields']['mukurtu_import_status']['label']);
    $this->assertSame(['title', 'mukurtu_import_status', 'status', 'type', 'changed'], array_keys($content['style']['options']['columns']));
    $this->assertArrayNotHasKey('timestamp', $content['style']['options']['info']);
    $this->assertNotContains('history', $this->loadView('mukurtu_import_results_content')->getDependencies()['module'] ?? []);

    $taxonomy = $this->displayOptions('mukurtu_import_results_taxonomy_terms');
    $this->assertSame(['name', 'mukurtu_import_status', 'vid', 'changed'], array_keys($taxonomy['fields']));
    $this->assertSame('taxonomy_term_field_data', $taxonomy['fields']['mukurtu_import_status']['table']);

    $media = $this->displayOptions('mukurtu_import_results_media');
    $this->assertSame(['name', 'mukurtu_import_status', 'status', 'bundle', 'changed'], array_keys($media['fields']));
    $this->assertSame('media_field_data', $media['fields']['mukurtu_import_status']['table']);
    $this->assertArrayHasKey('mukurtu_import_status', $media['style']['options']['info']);
  }

  /**
   * The updated views match what config/install ships to new sites.
   */
  public function testMatchesShippedConfig(): void {
    mukurtu_import_update_40403();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_import');
    foreach (['content', 'media', 'taxonomy_terms'] as $name) {
      $shipped = Yaml::decode(file_get_contents(\Drupal::root() . '/' . $module_path . "/config/install/views.view.mukurtu_import_results_$name.yml"));
      $this->assertEquals(
        $shipped['display']['default']['display_options']['fields'],
        $this->displayOptions("mukurtu_import_results_$name")['fields'],
        "The $name results view's fields match config/install.",
      );
    }
  }

  /**
   * A second run, and a deleted view, change nothing.
   */
  public function testIsIdempotentAndSkipsMissingViews(): void {
    $this->loadView('mukurtu_import_results_media')->delete();
    $this->assertNotNull(mukurtu_import_update_40403());
    $this->assertNull(mukurtu_import_update_40403());
    $this->assertNull($this->loadView('mukurtu_import_results_media'));
  }

  /**
   * Swaps the shipped Status field for an older column, or removes it.
   */
  protected function replaceStatusField(array &$options, array &$style, ?string $key, array $field): void {
    $rebuild = function (array $items, $value) use ($key) {
      $result = [];
      foreach ($items as $item_key => $item_value) {
        if ($item_key === 'mukurtu_import_status') {
          if ($key !== NULL) {
            $result[$key] = $value;
          }
          continue;
        }
        $result[$item_key] = $item_value;
      }
      return $result;
    };
    $options['fields'] = $rebuild($options['fields'], $field + $options['fields']['mukurtu_import_status']);
    $style['columns'] = $rebuild($style['columns'], $key);
    $style['info'] = $rebuild($style['info'], $style['info']['mukurtu_import_status']);
  }

  /**
   * Loads a view config entity.
   */
  protected function loadView(string $id) {
    return $this->entityTypeManager->getStorage('view')->loadUnchanged($id);
  }

  /**
   * Returns a view's default display options.
   */
  protected function displayOptions(string $id): array {
    return $this->loadView($id)->get('display')['default']['display_options'];
  }

}
