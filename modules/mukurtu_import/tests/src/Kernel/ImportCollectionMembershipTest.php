<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\mukurtu_core\ContainerMembershipFields;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\og\Entity\OgRole;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests adding items to collections from the item's own import row.
 *
 * Collection membership is stored on the collection, so the item's
 * "Collections" column (the computed field_in_collection) is applied to the
 * named collections after the item is saved.
 *
 * @see \Drupal\mukurtu_import\Plugin\migrate\destination\ProtocolAwareEntityContent::resolveMembershipChanges()
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2798
 */
#[Group('mukurtu_import')]
class ImportCollectionMembershipTest extends MukurtuImportTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'path',
    'path_alias',
    'mukurtu_collection',
    'mukurtu_drafts',
    'mukurtu_local_contexts',
  ];

  /**
   * The mapping used by most tests.
   */
  protected const MAPPING = [
    ['target' => 'nid', 'source' => 'ID'],
    ['target' => 'title', 'source' => 'Title'],
    ['target' => 'field_cultural_protocols/protocols', 'source' => 'Protocols'],
    ['target' => 'field_cultural_protocols/sharing_setting', 'source' => 'Sharing Setting'],
    ['target' => 'field_in_collection', 'source' => 'Collections'],
  ];

  /**
   * A collection the importing user can edit.
   */
  protected NodeInterface $collection;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('path_alias');

    // Person is one of the bundles collections accept; page isn't.
    NodeType::create(['type' => 'collection', 'name' => 'Collection'])->save();
    NodeType::create(['type' => 'person', 'name' => 'Person'])->save();
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    Vocabulary::create(['vid' => 'keywords', 'name' => 'Keywords'])->save();
    Vocabulary::create(['vid' => 'location', 'name' => 'Location'])->save();

    $role = Role::load('authenticated');
    $role->grantPermission('create person content');
    $role->save();

    // Without a group audience field, OG names node permissions
    // "<operation> <bundle> node".
    $steward = OgRole::load('protocol-protocol-protocol_steward');
    foreach (['create person node', 'update any person node', 'update own person node', 'update any collection node', 'update own collection node'] as $permission) {
      $steward->grantPermission($permission);
    }
    $steward->save();

    $this->collection = $this->createCollection('Collection A', $this->protocol);
  }

  /**
   * A new item is added to a collection named by title, ignoring case.
   */
  public function testNewItemAddedByTitle(): void {
    $this->importRows([['', 'Item 1', $this->protocol->id(), 'any', 'collection a']]);

    $item = $this->loadByTitle('Item 1');
    $this->assertSame([(int) $item->id()], $this->itemIds($this->collection));

    $latest = $this->latestRevision($this->collection);
    $this->assertSame('Added Item 1 to collection.', $latest->getRevisionLogMessage());
    $this->assertSame((int) $this->currentUser->id(), (int) $latest->getRevisionUserId());

    // The computed reverse field now reports the membership too.
    $this->assertSame([(string) $this->collection->id()], array_map('strval', array_column($item->get('field_in_collection')->getValue(), 'target_id')));
  }

  /**
   * Collections can be named by ID or UUID, several to a cell.
   */
  public function testIdAndUuid(): void {
    $other = $this->createCollection('Collection B', $this->protocol);
    $this->importRows([['', 'Item 1', $this->protocol->id(), 'any', $this->collection->id() . ';' . $other->uuid()]]);

    $item_id = (int) $this->loadByTitle('Item 1')->id();
    $this->assertSame([$item_id], $this->itemIds($this->collection));
    $this->assertSame([$item_id], $this->itemIds($other));
  }

  /**
   * An existing item is appended after the collection's current items.
   */
  public function testExistingItemAppended(): void {
    $first = $this->createPerson('First');
    $this->collection->get('field_items_in_collection')->appendItem(['target_id' => $first->id()]);
    $this->collection->save();
    $second = $this->createPerson('Second');

    $result = $this->importRows([[$second->id(), 'Second', $this->protocol->id(), 'any', 'Collection A']]);

    $this->assertSame([(int) $first->id(), (int) $second->id()], $this->itemIds($this->collection));
    $this->assertSame('updated', $result[0]['outcome'], 'A membership change alone makes the row Updated.');
  }

  /**
   * Re-importing an item that's already in the collection changes nothing.
   */
  public function testReimportIsUnchanged(): void {
    $this->importRows([['', 'Item 1', $this->protocol->id(), 'any', 'Collection A']]);
    $item = $this->loadByTitle('Item 1');
    $revision = $this->latestRevision($this->collection)->getRevisionId();

    $result = $this->importRows([[$item->id(), 'Item 1', $this->protocol->id(), 'any', 'Collection A;Collection A']]);

    $this->assertSame([(int) $item->id()], $this->itemIds($this->collection));
    $this->assertSame($revision, $this->latestRevision($this->collection)->getRevisionId(), 'The collection must not be re-saved.');
    $this->assertSame('unchanged', $result[0]['outcome']);
  }

  /**
   * A blank cell, or one with only delimiters, changes nothing.
   */
  public function testBlankCellChangesNothing(): void {
    $item = $this->createPerson('Kept');
    $this->collection->get('field_items_in_collection')->appendItem(['target_id' => $item->id()]);
    $this->collection->save();

    $this->importRows([
      [$item->id(), 'Kept', $this->protocol->id(), 'any', ''],
      ['', 'Item 2', $this->protocol->id(), 'any', ' ; '],
    ]);

    $this->assertSame([(int) $item->id()], $this->itemIds($this->collection));
    $this->assertNotNull($this->loadByTitle('Item 2'));
    $this->assertEmpty(iterator_to_array($this->lastMigration->getIdMap()->getMessages()));
  }

  /**
   * A value that names no collection fails the row, and nothing is saved.
   */
  public function testUnknownCollectionFailsRow(): void {
    // A real node that isn't a collection doesn't count, by ID or title.
    $person = $this->createPerson('Not A Collection');

    $this->importRows([
      ['', 'Item 1', $this->protocol->id(), 'any', 'Collection A;Colection B'],
      ['', 'Item 2', $this->protocol->id(), 'any', (string) $person->id()],
      ['', 'Item 3', $this->protocol->id(), 'any', 'Not A Collection'],
    ]);

    foreach (['Item 1', 'Item 2', 'Item 3'] as $title) {
      $this->assertNull($this->loadByTitle($title), "$title must not be created.");
    }
    $this->assertSame([], $this->itemIds($this->collection));
    $this->assertSame([
      'No collection matches "Colection B". Use the collection\'s title, ID, or UUID.',
      sprintf('No collection matches "%s". Use the collection\'s title, ID, or UUID.', $person->id()),
      'No collection matches "Not A Collection". Use the collection\'s title, ID, or UUID.',
    ], $this->messages());
  }

  /**
   * A title shared by two collections is ambiguous.
   */
  public function testAmbiguousTitleFailsRow(): void {
    $this->createCollection('Collection A', $this->protocol);

    $this->importRows([['', 'Item 1', $this->protocol->id(), 'any', 'Collection A']]);

    $this->assertNull($this->loadByTitle('Item 1'));
    $this->assertSame(['"Collection A" is ambiguous, multiple items share the same label. Try referencing the item\'s ID.'], $this->messages());
  }

  /**
   * A collection the importer can't edit fails the row, unless the item is
   * already in it.
   */
  public function testCollectionWithoutAccess(): void {
    $already_in = $this->createPerson('Already In');
    $this->collection->get('field_items_in_collection')->appendItem(['target_id' => $already_in->id()]);
    $this->collection->save();

    // A protocol member who can edit people but not collections.
    $outsider = $this->createPersonEditor();
    $this->assertTrue($already_in->access('update', $outsider));
    $this->assertFalse($this->collection->access('update', $outsider));
    $this->setCurrentUser($outsider);
    $this->currentUser = $outsider;

    $this->importRows([
      ['', 'Outsider Item', $this->protocol->id(), 'any', 'Collection A'],
      [$already_in->id(), 'Already In', $this->protocol->id(), 'any', 'Collection A'],
    ]);

    $this->assertNull($this->loadByTitle('Outsider Item'), 'A row that fails on collection access must not create its item.');
    $this->assertSame([(int) $already_in->id()], $this->itemIds($this->collection));
    $this->assertSame(['The current user does not have permission to add items to the collection "Collection A".'], $this->messages());
  }

  /**
   * Only bundles a collection accepts get the column, and never collections.
   */
  public function testEligibleBundles(): void {
    $field_manager = $this->container->get('entity_field.manager');
    $this->assertNotNull(ContainerMembershipFields::forItemBundle($field_manager, 'field_in_collection', 'person'));
    $this->assertNull(ContainerMembershipFields::forItemBundle($field_manager, 'field_in_collection', 'page'));
    $this->assertNull(ContainerMembershipFields::forItemBundle($field_manager, 'field_in_collection', 'collection'));
    $this->assertNull(ContainerMembershipFields::forItemBundle($field_manager, 'field_in_word_list', 'person'), 'Word lists are not installed here.');
    $this->assertNull(ContainerMembershipFields::forItemBundle($field_manager, 'field_title', 'person'));
  }

  /**
   * Imports rows of [ID, Title, Protocols, Sharing Setting, Collections].
   *
   * @return array
   *   The destination's recorded row results.
   */
  protected function importRows(array $rows): array {
    $file = $this->createCsvFile(array_merge([['ID', 'Title', 'Protocols', 'Sharing Setting', 'Collections']], $rows));
    $result = $this->importCsvFile($file, self::MAPPING, 'node', 'person');
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    return $this->lastMigration->getDestinationPlugin()->getAndClearRowResults();
  }

  /**
   * Returns the messages the last import recorded, in row order.
   */
  protected function messages(): array {
    return array_map(fn ($m) => (string) $m->message, iterator_to_array($this->lastMigration->getIdMap()->getMessages()));
  }

  /**
   * Creates and saves a collection in a protocol.
   */
  protected function createCollection(string $title, Protocol $protocol): NodeInterface {
    $collection = Node::create([
      'type' => 'collection',
      'title' => $title,
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $collection->setSharingSetting('any');
    $collection->setProtocols([$protocol]);
    $collection->save();
    return $collection;
  }

  /**
   * Creates and saves a person node owned by the current user.
   */
  protected function createPerson(string $title): NodeInterface {
    $node = Node::create([
      'type' => 'person',
      'title' => $title,
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    return $node;
  }

  /**
   * Creates a member of the protocol who can edit people, not collections.
   */
  protected function createPersonEditor(): AccountInterface {
    $role = OgRole::create([
      'name' => 'person_editor',
      'label' => 'Person editor',
      'permissions' => ['create person node', 'edit any person content'],
    ]);
    $role->setGroupType('protocol');
    $role->setGroupBundle('protocol');
    $role->save();

    $user = $this->createUser(['name' => 'person_editor']);
    $this->community->addMember($user);
    $this->protocol->addMember($user, ['person_editor']);
    return $user;
  }

  /**
   * Loads the person with this title, or NULL.
   */
  protected function loadByTitle(string $title): ?NodeInterface {
    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties(['type' => 'person', 'title' => $title]);
    return $nodes ? reset($nodes) : NULL;
  }

  /**
   * Returns a collection's item IDs, reloaded from storage.
   */
  protected function itemIds(NodeInterface $collection): array {
    $collection = $this->entityTypeManager->getStorage('node')->loadUnchanged($collection->id());
    return array_map('intval', array_column($collection->get('field_items_in_collection')->getValue(), 'target_id'));
  }

  /**
   * Loads a node's latest revision.
   */
  protected function latestRevision(NodeInterface $node): NodeInterface {
    $storage = $this->entityTypeManager->getStorage('node');
    return $storage->loadRevision($storage->getLatestRevisionId($node->id()));
  }

}
