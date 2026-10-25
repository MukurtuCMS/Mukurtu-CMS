<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_collection\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\mukurtu_collection\Entity\Collection;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Add to Collection bulk action.
 */
#[Group('mukurtu_collection')]
class AddToCollectionActionTest extends CollectionTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['views_bulk_operations'];

  /**
   * A protocol steward who isn't uid 1, so access checks really run.
   */
  protected UserInterface $editor;

  /**
   * A collection the editor can update.
   */
  protected Collection $collection;

  /**
   * {@inheritdoc}
   */
  protected function getProtocolStewardPermissions(): array {
    // Without a group audience field, OG names the update permission
    // "update any <bundle> node", not the "edit any" form the base grants.
    return array_merge(parent::getProtocolStewardPermissions(), [
      'update any collection node',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // A bundle collections accept; page (from the base) isn't one.
    NodeType::create(['type' => 'person', 'name' => 'Person'])->save();

    $this->collection = $this->buildCollection('Collection A');
    $this->collection->save();

    $this->editor = User::create(['name' => 'editor']);
    $this->editor->save();
    $this->community->addMember($this->editor);
    $this->protocol->addMember($this->editor, ['protocol_steward']);
    $this->container->get('current_user')->setAccount($this->editor);
  }

  /**
   * Returns a configured action instance.
   */
  protected function action(?int $collection_id = NULL) {
    $action = $this->container->get('plugin.manager.views_bulk_operations_action')
      ->createInstance('mukurtu_add_to_collection_action');
    $action->setConfiguration(['container' => $collection_id ?? $this->collection->id()]);
    return $action;
  }

  /**
   * Creates and saves a person node.
   */
  protected function person(string $title): Node {
    $node = Node::create(['type' => 'person', 'title' => $title, 'status' => TRUE, 'uid' => $this->currentUser->id()]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    return $node;
  }

  /**
   * Returns the item IDs in the collection, reloaded from storage.
   */
  protected function itemIds(): array {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$this->collection->id()]);
    return array_map('intval', array_column($storage->load($this->collection->id())->get(MUKURTU_COLLECTION_FIELD_NAME_ITEMS)->getValue(), 'target_id'));
  }

  /**
   * The editor is a real, non-admin account.
   */
  public function testEditorIsNotUidOne(): void {
    $this->assertNotEquals(1, (int) $this->editor->id());
    $this->assertTrue($this->collection->access('update', $this->editor));
  }

  /**
   * Items are added in order, with one new revision for the whole batch.
   */
  public function testAddsItemsInOneRevision(): void {
    $a = $this->person('A');
    $b = $this->person('B');
    $revisions_before = count($this->container->get('entity_type.manager')->getStorage('node')->revisionIds($this->collection));

    $results = $this->action()->executeMultiple([$a, $b]);

    $this->assertSame([(int) $a->id(), (int) $b->id()], $this->itemIds());
    $this->assertSame(['status', 'status'], array_column($results, 'type'));
    $this->assertSame('Added to Collection A', $results[0]['message']);

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $this->assertCount($revisions_before + 1, $storage->revisionIds($this->collection));
    $latest = $storage->loadRevision($storage->getLatestRevisionId($this->collection->id()));
    $this->assertSame('Added 2 items in a bulk operation.', $latest->getRevisionLogMessage());
    $this->assertSame((int) $this->editor->id(), (int) $latest->getRevisionUserId());
  }

  /**
   * Items already in the collection, or repeated in the batch, are skipped.
   */
  public function testSkipsDuplicates(): void {
    $a = $this->person('A');
    $b = $this->person('B');
    $this->action()->executeMultiple([$a]);

    // /admin/content can send the same node twice, one row per translation.
    $results = $this->action()->executeMultiple([$a, $b, $b]);

    $this->assertSame([(int) $a->id(), (int) $b->id()], $this->itemIds());
    $this->assertSame(['warning', 'status', 'warning'], array_column($results, 'type'));
    $this->assertSame('Already in Collection A', $results[0]['message']);
  }

  /**
   * Nothing is saved when every item is already present.
   */
  public function testNoRevisionWhenNothingAdded(): void {
    $a = $this->person('A');
    $this->action()->executeMultiple([$a]);
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $revisions_before = count($storage->revisionIds($this->collection));

    $this->action()->executeMultiple([$a]);

    $this->assertCount($revisions_before, $storage->revisionIds($this->collection));
  }

  /**
   * A collection the editor can't update is refused for every item.
   */
  public function testRefusesCollectionWithoutUpdateAccess(): void {
    $outsider = User::create(['name' => 'outsider']);
    $outsider->save();
    $this->container->get('current_user')->setAccount($outsider);
    $a = $this->person('A');

    $results = $this->action()->executeMultiple([$a]);

    $this->assertSame([], $this->itemIds());
    $this->assertSame('error', $results[0]['type']);
    $this->assertSame("Couldn't add to Collection A", $results[0]['message']);
  }

  /**
   * Only bundles a collection accepts pass the per-item access check.
   */
  public function testAccessRequiresEligibleBundle(): void {
    $person = $this->person('Person');
    $page = $this->buildItem('Page');
    $page->save();

    $this->assertTrue($this->action()->access($person, $this->editor));

    $result = $this->action()->access($page, $this->editor, TRUE);
    $this->assertTrue($result->isForbidden());
    $this->assertSame("This type of content can't be added to a collection.", $result->getReason());
  }

  /**
   * The picker lists only collections the editor can update.
   */
  public function testPickerListsUpdatableCollections(): void {
    $hidden = $this->buildCollection('Collection B');
    $hidden->setProtocols([]);
    $hidden->save();

    $form = $this->action()->buildConfigurationForm(['actions' => ['submit' => []]], new FormState());

    $this->assertSame('select', $form['container']['#type']);
    $this->assertSame([$this->collection->id() => 'Collection A'], $form['container']['#options']);
  }

  /**
   * With nothing to pick, the form explains why and hides Apply.
   */
  public function testPickerWithNoCollections(): void {
    $outsider = User::create(['name' => 'outsider']);
    $outsider->save();
    $this->container->get('current_user')->setAccount($outsider);

    $form = $this->action()->buildConfigurationForm(['actions' => ['submit' => []]], new FormState());

    $this->assertSame('item', $form['container']['#type']);
    $this->assertSame('There are no collections you can add items to.', (string) $form['container']['#markup']);
    $this->assertFalse($form['actions']['submit']['#access']);
  }

}
