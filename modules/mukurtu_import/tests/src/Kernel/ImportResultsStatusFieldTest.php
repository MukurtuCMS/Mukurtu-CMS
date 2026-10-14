<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\Core\Serialization\Yaml;
use Drupal\mukurtu_import\Plugin\views\field\ImportStatus;
use Drupal\node\Entity\Node;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Status column of the shipped content import results view.
 *
 * @see \Drupal\mukurtu_import\Plugin\views\field\ImportStatus
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2309
 */
#[Group('mukurtu_import')]
class ImportResultsStatusFieldTest extends MukurtuImportTestBase {

  /**
   * The revision log message the import gives every item it saves.
   */
  protected const MESSAGE = 'Imported by tester (Import ID: 2309)';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create the view from its shipped YAML, the same way the update hook
    // tests create import templates, rather than installing the module's
    // whole config set.
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_import');
    $values = Yaml::decode(file_get_contents(\Drupal::root() . '/' . $module_path . '/config/install/views.view.mukurtu_import_results_content.yml'));
    unset($values['langcode'], $values['status'], $values['dependencies']);
    $this->entityTypeManager->getStorage('view')->create($values)->save();
  }

  /**
   * The column shows the recorded outcome of each item, independent of
   * whether the current user has viewed it.
   */
  public function testStatusColumnShowsRecordedOutcome(): void {
    $new = $this->createImportedNode('New Item');
    $updated = $this->createImportedNode('Updated Item');
    $unchanged = $this->createImportedNode('Unchanged Item');
    $unrecorded = $this->createImportedNode('Unrecorded Item');

    \Drupal::service('tempstore.private')->get('mukurtu_import')->set(ImportStatus::TEMPSTORE_KEY, [
      'node' => [
        (string) $new->id() => [$new->language()->getId() => 'new'],
        (string) $updated->id() => [$updated->language()->getId() => 'updated'],
        (string) $unchanged->id() => [$unchanged->language()->getId() => 'unchanged'],
      ],
    ]);

    $this->assertEquals([
      'New Item' => 'New',
      'Updated Item' => 'Updated',
      'Unchanged Item' => 'Unchanged',
      // Nothing recorded, e.g. after the tempstore expired: no guess.
      'Unrecorded Item' => '',
    ], $this->renderStatusColumn());
  }

  /**
   * A translation the import didn't touch is reported as Unchanged.
   */
  public function testUnrecordedLanguageOfRecordedItemIsUnchanged(): void {
    $node = $this->createImportedNode('Other Language Item');

    \Drupal::service('tempstore.private')->get('mukurtu_import')->set(ImportStatus::TEMPSTORE_KEY, [
      'node' => [(string) $node->id() => ['xx' => 'updated']],
    ]);

    $this->assertEquals(['Other Language Item' => 'Unchanged'], $this->renderStatusColumn());
  }

  /**
   * Creates a node whose current revision carries the import's log message.
   */
  protected function createImportedNode(string $title): Node {
    $node = Node::create([
      'title' => $title,
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
      'revision_log' => self::MESSAGE,
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    return $node;
  }

  /**
   * Runs the results display and returns each row's rendered status.
   *
   * @return array
   *   Rendered Status values keyed by node title.
   */
  protected function renderStatusColumn(): array {
    $view = Views::getView('mukurtu_import_results_content');
    $view->setDisplay('results');
    $view->setArguments([self::MESSAGE]);
    $view->execute();

    $statuses = [];
    foreach ($view->result as $row) {
      $statuses[$row->_entity->label()] = (string) $view->field['mukurtu_import_status']->render($row);
    }
    return $statuses;
  }

}
