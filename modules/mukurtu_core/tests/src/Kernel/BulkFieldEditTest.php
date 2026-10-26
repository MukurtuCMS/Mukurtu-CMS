<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityFormMode;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeAccessRebuild;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Mukurtu's adjustments to the "Modify field values" bulk action.
 *
 * @see \Drupal\mukurtu_core\BulkEdit\MukurtuModifyEntityValues
 */
#[Group('mukurtu_core')]
class BulkFieldEditTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'geofield',
    'image',
    'leaflet',
    'media',
    'mukurtu_core',
    'mukurtu_protocol',
    'node',
    'og',
    'options',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'views_bulk_edit',
    'views_bulk_operations',
  ];

  /**
   * An editor with "Use bulk editing" who isn't uid 1.
   */
  protected UserInterface $editor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('node', ['node_access']);
    $this->installSchema('system', ['sequences']);
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_map');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_access');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');
    $this->installEntitySchema('og_membership');
    \Drupal::service(NodeAccessRebuild::class)->rebuild();

    // Burn uid 1, which bypasses every permission.
    User::create(['name' => 'admin'])->save();
    Role::create(['id' => 'bulk', 'label' => 'Bulk'])
      ->grantPermission('use mukurtu bulk edit')
      ->grantPermission('access content')
      ->save();
    $this->editor = User::create(['name' => 'editor', 'roles' => ['bulk']]);
    $this->editor->save();
    $this->container->get('current_user')->setAccount($this->editor);

    NodeType::create(['type' => 'article', 'name' => 'Article', 'new_revision' => FALSE])->save();
    FieldStorageConfig::create(['field_name' => 'field_summary', 'entity_type' => 'node', 'type' => 'string'])->save();
    FieldConfig::create([
      'field_name' => 'field_summary',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Summary',
      'required' => TRUE,
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_related',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => ['target_type' => 'node'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_related',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Related content',
    ])->save();
  }

  /**
   * Creates and saves an article.
   */
  protected function article(string $title): NodeInterface {
    $node = Node::create(['type' => 'article', 'title' => $title, 'field_summary' => 'Original']);
    $node->save();
    return $node;
  }

  /**
   * Returns the action configured to change the given fields.
   */
  protected function action(array $values, array $methods = []) {
    $action = $this->container->get('plugin.manager.views_bulk_operations_action')->createInstance('views_bulk_edit');
    $action->setConfiguration([
      'node' => [
        'article' => [
          'values' => $values,
          'change_method' => $methods + array_fill_keys(array_keys($values), 'replace'),
          'revision_information' => ['revision' => FALSE],
        ],
      ],
    ]);
    return $action;
  }

  /**
   * Reloads a node from storage.
   */
  protected function reload(NodeInterface $node): NodeInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$node->id()]);
    return $storage->load($node->id());
  }

  /**
   * The action needs "Use bulk editing" as well as edit access.
   */
  public function testAccessNeedsPermission(): void {
    $node = $this->article('A');
    $without = User::create(['name' => 'without']);
    $without->save();

    $result = $this->action([])->access($node, $without, TRUE);
    $this->assertTrue($result->isForbidden());
  }

  /**
   * Every change is saved as a new revision, even when not asked for.
   */
  public function testAlwaysCreatesRevision(): void {
    $node = $this->article('A');
    $revisions = fn() => (int) $this->container->get('entity_type.manager')->getStorage('node')->getQuery()
      ->allRevisions()
      ->accessCheck(FALSE)
      ->condition('nid', $node->id())
      ->count()
      ->execute();
    $before = $revisions();

    $this->action(['field_summary' => [['value' => 'Bulk']]])->execute($node);

    $this->assertSame($before + 1, $revisions());
    $this->assertSame('Bulk', $this->reload($node)->get('field_summary')->value);
  }

  /**
   * An item is never made to reference itself.
   */
  public function testDropsSelfReferences(): void {
    $a = $this->article('A');
    $b = $this->article('B');
    $c = $this->article('C');
    $value = [['target_id' => $a->id()], ['target_id' => $c->id()]];

    $this->action(['field_related' => $value])->execute($a);
    $this->action(['field_related' => $value])->execute($b);

    $ids = fn(NodeInterface $node) => array_map('intval', array_column($this->reload($node)->get('field_related')->getValue(), 'target_id'));
    $this->assertSame([(int) $c->id()], $ids($a));
    $this->assertSame([(int) $a->id(), (int) $c->id()], $ids($b));
  }

  /**
   * A change that leaves a required field empty is refused for that item.
   */
  public function testValidatesChangedFields(): void {
    $node = $this->article('A');

    $result = $this->action(['field_summary' => []])->execute($node);

    $this->assertSame('error', $result['type']);
    $this->assertStringStartsWith("Couldn't change A:", $result['message']);
    $this->assertSame('Original', $this->reload($node)->get('field_summary')->value);
  }

  /**
   * The form labels each change method and hides the revision checkbox.
   */
  public function testFormAdjustments(): void {
    EntityFormMode::create(['id' => 'node.bulk_edit', 'label' => 'Bulk edit', 'targetEntityType' => 'node'])->save();
    $display = EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'article',
      'mode' => 'bulk_edit',
      'status' => TRUE,
    ]);
    foreach (array_keys($display->getComponents()) as $name) {
      $display->removeComponent($name);
    }
    $display
      ->setComponent('field_related', ['type' => 'entity_reference_autocomplete'])
      ->save();

    $action = $this->action([]);
    $method = new \ReflectionMethod($action, 'getBundleForm');
    $form = $method->invoke($action, 'node', 'article', 'Article', [], new FormState(), 1);
    $section = $form['node']['article'];

    $this->assertSame('Change method for Related content', (string) $section['field_related_change_method']['#title']);
    $this->assertFalse($section['revision_information']['revision']['#access']);
    $this->assertTrue($section['revision_information']['revision']['#value']);
    $this->assertArrayNotHasKey('#states', $section['revision_information']['revision_log']);
    $this->assertStringStartsWith('Translated fields change only', (string) $section['mukurtu_translation_help']['#value']);
  }

}
