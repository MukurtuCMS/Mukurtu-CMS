<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;

/**
 * Tests accessibility markup on the Import Template mapping table.
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1976
 */
class MukurtuImportStrategyFormAccessibilityTest extends MukurtuImportTestBase {

  /**
   * Tests Remove button labeling and the AJAX status/focus scaffolding.
   */
  public function testMappingTableAccessibilityMarkup(): void {
    $entity = MukurtuImportStrategy::create([
      'id' => 'test_strategy',
      'label' => 'Test Strategy',
      'uid' => $this->currentUser->id(),
      'target_entity_type_id' => 'node',
      'target_bundle' => 'protocol_aware_content',
      'mapping' => [
        ['source' => 'Title', 'target' => 'title'],
        ['source' => 'Body', 'target' => 'body'],
      ],
      'configuration' => [],
    ]);
    $entity->save();

    $form = \Drupal::service('entity.form_builder')->getForm($entity, 'edit');

    // Each row's Remove button has a distinct, non-empty accessible name.
    $labels = [];
    foreach ([0, 1] as $delta) {
      $this->assertArrayHasKey($delta, $form['mapping']);
      $label = (string) ($form['mapping'][$delta]['remove']['#attributes']['aria-label'] ?? '');
      $this->assertNotSame('', $label, "Row $delta Remove button has an aria-label.");
      $labels[] = $label;
    }
    $this->assertSame(array_unique($labels), $labels, 'Remove button aria-labels are distinct per row.');

    // The live region is a sibling of the mapping table, not nested inside
    // the AJAX-replaced wrapper, so it survives the wrapper being swapped.
    $this->assertArrayNotHasKey('mapping_status', $form['mapping']);
    $this->assertSame('import-field-mapping-status', $form['mapping_status']['#attributes']['id']);
    $this->assertSame('polite', $form['mapping_status']['#attributes']['aria-live']);
    $this->assertSame('true', $form['mapping_status']['#attributes']['aria-atomic']);
    $this->assertContains('visually-hidden', $form['mapping_status']['#attributes']['class']);

    // The Add mapping button has a deterministic id for JS focus targeting.
    $this->assertSame('import-add-mapping-button', $form['add_mapping']['#attributes']['id']);
  }

  /**
   * Tests that only the Add/Remove AJAX callback marks the table as changed.
   *
   * The mapping-status JS behavior (strategy-form.js) relies on
   * mappingTableAjaxCallback() marking its returned table with
   * data-mapping-just-changed so it can tell a genuine Add/Remove rebuild
   * apart from the unrelated entity-type/bundle-change AJAX callbacks, which
   * replace the same #import-field-mapping-config wrapper for a different
   * reason and must not trigger the status announcement or focus move.
   */
  public function testMappingTableAjaxMarkerScoping(): void {
    $entity = MukurtuImportStrategy::create([
      'id' => 'test_strategy_2',
      'label' => 'Test Strategy 2',
      'uid' => $this->currentUser->id(),
      'target_entity_type_id' => 'node',
      'target_bundle' => 'protocol_aware_content',
      'mapping' => [
        ['source' => 'Title', 'target' => 'title'],
      ],
      'configuration' => [],
    ]);
    $entity->save();

    // Each AJAX callback below simulates a separate, independent HTTP
    // request in real usage, each rebuilding $form from scratch - so each
    // gets its own freshly-built $form/$form_state rather than reusing one
    // across calls (mappingTableAjaxCallback() mutates #attributes on the
    // $form array it's given by reference, which would otherwise leak the
    // marker into the other callbacks purely as a test artifact).
    $build = function () use ($entity): array {
      $form_object = \Drupal::entityTypeManager()->getFormObject('mukurtu_import_strategy', 'edit');
      $form_object->setEntity($entity);
      $form_state = new FormState();
      $form_state->setValue('target_entity_type_id', 'node');
      $form_state->setValue('target_bundle', 'protocol_aware_content');
      $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);
      return [$form_object, $form, $form_state];
    };

    // The Add/Remove callback marks the table it returns.
    [$form_object, $form, $form_state] = $build();
    $mapping_result = $form_object->mappingTableAjaxCallback($form, $form_state);
    $this->assertSame('true', $mapping_result['#attributes']['data-mapping-just-changed'] ?? NULL);

    // The entity-type-change callback replaces the same wrapper, but must
    // not carry the marker.
    [$form_object, $form, $form_state] = $build();
    $response = $form_object->entityTypeChangeAjaxCallback($form, $form_state);
    $rendered = $this->getReplaceCommandDataForSelector($response, '#import-field-mapping-config');
    $this->assertIsString($rendered);
    $this->assertStringNotContainsString('data-mapping-just-changed', $rendered);

    // Same for the bundle-change callback.
    [$form_object, $form, $form_state] = $build();
    $response = $form_object->bundleChangeAjaxCallback($form, $form_state);
    $rendered = $this->getReplaceCommandDataForSelector($response, '#import-field-mapping-config');
    $this->assertIsString($rendered);
    $this->assertStringNotContainsString('data-mapping-just-changed', $rendered);
  }

  /**
   * Extracts the rendered 'data' payload for a ReplaceCommand by selector.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The AJAX response to inspect.
   * @param string $selector
   *   The CSS selector the command targets.
   *
   * @return string|null
   *   The rendered HTML for the matching command, or NULL if not found.
   */
  protected function getReplaceCommandDataForSelector($response, string $selector): ?string {
    $commands = (new \ReflectionProperty($response, 'commands'))->getValue($response);
    foreach ($commands as $command) {
      if (($command['selector'] ?? NULL) === $selector) {
        return isset($command['data']) ? (string) $command['data'] : NULL;
      }
    }
    return NULL;
  }

}
