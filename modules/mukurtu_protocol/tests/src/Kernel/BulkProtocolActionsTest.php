<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\mukurtu_protocol\Hook\BulkProtocolConfirmHooks;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the bulk Add/Remove Cultural Protocol and Change Sharing actions.
 */
#[Group('mukurtu_protocol')]
class BulkProtocolActionsTest extends ProtocolAwareEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['views_bulk_operations'];

  /**
   * A protocol steward of A and C only, who isn't uid 1.
   */
  protected UserInterface $editor;

  /**
   * Strict protocols A, B and C, and open protocol O.
   *
   * @var \Drupal\mukurtu_protocol\Entity\Protocol[]
   */
  protected array $p = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $community = Community::create(['name' => 'Community']);
    $community->save();
    foreach (['A' => 'strict', 'B' => 'strict', 'C' => 'strict', 'O' => 'open'] as $name => $mode) {
      $this->p[$name] = Protocol::create([
        'name' => "Protocol $name",
        'field_communities' => [$community->id()],
        'field_access_mode' => $mode,
      ]);
      $this->p[$name]->save();
    }

    $this->editor = $this->createUser(['use mukurtu bulk edit']);
    $community->addMember($this->editor);
    $this->p['A']->addMember($this->editor, ['protocol_steward']);
    $this->p['C']->addMember($this->editor, ['protocol_steward']);
    $this->setCurrentUser($this->editor);
  }

  /**
   * Creates an item owned by uid 1 with the given protocols and sharing.
   */
  protected function item(array $protocols, string $sharing): NodeInterface {
    $node = Node::create([
      'type' => 'protocol_aware_content',
      'title' => implode('+', $protocols) . " $sharing",
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting($sharing);
    $node->setProtocols(array_map(fn($name) => $this->p[$name], $protocols));
    // Save as uid 1, who may apply any protocol.
    $this->setCurrentUser($this->currentUser);
    $node->save();
    $this->setCurrentUser($this->editor);
    return $node;
  }

  /**
   * Returns a configured action.
   */
  protected function action(string $id, array $configuration) {
    return $this->container->get('plugin.manager.views_bulk_operations_action')->createInstance($id, $configuration);
  }

  /**
   * Returns protocol names on the reloaded item, and its sharing setting.
   */
  protected function state(NodeInterface $node): array {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$node->id()]);
    $node = $storage->load($node->id());
    $names = array_map(fn($id) => array_search((int) $id, array_map(fn($p) => (int) $p->id(), $this->p)), $node->getProtocols());
    sort($names);
    return [$names, $node->getSharingSetting()];
  }

  /**
   * Returns an item's loss notices as strings.
   */
  protected function notices(string $id, array $configuration, NodeInterface $node): array {
    return array_map('strval', $this->action($id, $configuration)->lossNotices($node, $this->editor));
  }

  /**
   * The editor is a real, non-admin account that can edit A items.
   */
  public function testEditorIsNotUidOne(): void {
    $this->assertNotEquals(1, (int) $this->editor->id());
    $this->assertTrue($this->item(['A'], 'any')->access('update', $this->editor));
  }

  /**
   * Adding keeps the sharing setting and logs a revision.
   */
  public function testAddProtocol(): void {
    $node = $this->item(['A'], 'all');
    $action = $this->action('mukurtu_add_protocol_action', ['protocol' => (int) $this->p['C']->id()]);

    $this->assertSame(['message' => 'Protocol added', 'type' => 'status'], $action->execute($node));
    $this->assertSame([['A', 'C'], 'all'], $this->state($node));
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $latest = $storage->loadRevision($storage->getLatestRevisionId($node->id()));
    $this->assertSame('Added Protocol C in a bulk operation.', $latest->getRevisionLogMessage());
    $this->assertSame((int) $this->editor->id(), (int) $latest->getRevisionUserId());

    $again = $this->action('mukurtu_add_protocol_action', ['protocol' => (int) $this->p['C']->id()])->execute($this->reload($node));
    $this->assertSame(['message' => 'A all: Already has this protocol', 'type' => 'warning'], $again);
  }

  /**
   * Removing skips items without the protocol, or with only that protocol.
   */
  public function testRemoveProtocol(): void {
    $both = $this->item(['A', 'C'], 'any');
    $only = $this->item(['C'], 'any');
    $without = $this->item(['A'], 'any');
    $configuration = ['protocol' => (int) $this->p['C']->id()];

    $this->assertSame('Protocol removed', $this->action('mukurtu_remove_protocol_action', $configuration)->execute($both)['message']);
    $this->assertSame([['A'], 'any'], $this->state($both));
    $this->assertSame("C any: Can't remove the item's only protocol", $this->action('mukurtu_remove_protocol_action', $configuration)->execute($only)['message']);
    $this->assertSame([['C'], 'any'], $this->state($only));
    $this->assertSame("A any: Doesn't have this protocol", $this->action('mukurtu_remove_protocol_action', $configuration)->execute($without)['message']);
  }

  /**
   * Changing sharing keeps protocols the editor isn't a member of.
   */
  public function testChangeSharingKeepsUnseenProtocols(): void {
    $node = $this->item(['A', 'B'], 'any');

    $result = $this->action('mukurtu_change_sharing_action', ['sharing' => 'all'])->execute($node);

    $this->assertSame('Sharing setting changed', $result['message']);
    $this->assertSame([['A', 'B'], 'all'], $this->state($node));
    $again = $this->action('mukurtu_change_sharing_action', ['sharing' => 'all'])->execute($this->reload($node));
    $this->assertSame('A+B any: Already uses this sharing setting', $again['message']);
  }

  /**
   * A protocol the editor can't apply is refused by preSave and reported.
   */
  public function testProtocolEditorCannotApplyIsReported(): void {
    $node = $this->item(['A'], 'any');

    $result = $this->action('mukurtu_add_protocol_action', ['protocol' => (int) $this->p['B']->id()])->execute($node);

    $this->assertSame(['message' => "Couldn't change A any", 'type' => 'error'], $result);
    $this->assertSame([['A'], 'any'], $this->state($node));
  }

  /**
   * Only editors with "Use bulk editing" pass, for items they can update.
   */
  public function testAccess(): void {
    $action = $this->action('mukurtu_change_sharing_action', ['sharing' => 'all']);

    $this->assertTrue($action->access($this->item(['A'], 'any'), $this->editor));
    $this->assertFalse($action->access($this->item(['B'], 'any'), $this->editor));

    // A fellow steward of A, but without the permission.
    $steward = $this->createUser();
    $this->p['A']->addMember($steward, ['protocol_steward']);
    $this->assertTrue($this->item(['A'], 'any')->access('update', $steward));
    $this->assertTrue($action->access($this->item(['A'], 'any'), $steward, TRUE)->isForbidden());

    $result = $action->access($this->editor, $this->editor, TRUE);
    $this->assertTrue($result->isForbidden());
    $this->assertSame("This content doesn't use cultural protocols.", $result->getReason());
  }

  /**
   * Removing with "any" warns the editor and members of that protocol.
   */
  public function testRemoveNotices(): void {
    $node = $this->item(['A', 'B'], 'any');

    $this->assertSame([
      'A+B any: you will no longer be able to see it.',
      "A+B any: members of Protocol A who aren't in its other protocols will lose access.",
    ], $this->notices('mukurtu_remove_protocol_action', ['protocol' => (int) $this->p['A']->id()], $node));

    // With "all", removing a protocol only widens access.
    $c = ['protocol' => (int) $this->p['C']->id()];
    $this->assertSame([], $this->notices('mukurtu_remove_protocol_action', $c, $this->item(['A', 'C'], 'all')));
  }

  /**
   * Adding with "all" warns members of the other protocols; "any" doesn't.
   */
  public function testAddNotices(): void {
    $configuration = ['protocol' => (int) $this->p['C']->id()];

    $this->assertSame(
      ["A all: members who aren't also in Protocol C will lose access."],
      $this->notices('mukurtu_add_protocol_action', $configuration, $this->item(['A'], 'all')),
    );
    $this->assertSame([], $this->notices('mukurtu_add_protocol_action', $configuration, $this->item(['A'], 'any')));
  }

  /**
   * Moving to "all" warns when there's more than one protocol.
   */
  public function testSharingNotices(): void {
    $this->assertSame(
      ["A+C any: members who aren't in all of its protocols will lose access."],
      $this->notices('mukurtu_change_sharing_action', ['sharing' => 'all'], $this->item(['A', 'C'], 'any')),
    );
    $this->assertSame([], $this->notices('mukurtu_change_sharing_action', ['sharing' => 'all'], $this->item(['A'], 'any')));
    $this->assertSame([], $this->notices('mukurtu_change_sharing_action', ['sharing' => 'any'], $this->item(['A', 'C'], 'all')));
  }

  /**
   * Losing an open protocol is reported as "no longer visible to everyone".
   */
  public function testOpenProtocolNotice(): void {
    // "All" needs edit permission in every protocol, and membership of an
    // open protocol carries no role, so the editor loses edit access too.
    $this->assertSame([
      'A+O any: you will no longer be able to edit it.',
      'A+O any: will no longer be visible to everyone.',
    ], $this->notices('mukurtu_change_sharing_action', ['sharing' => 'all'], $this->item(['A', 'O'], 'any')));
    $this->assertSame(
      ['O all: will no longer be visible to everyone.'],
      $this->notices('mukurtu_add_protocol_action', ['protocol' => (int) $this->p['A']->id()], $this->item(['O'], 'all')),
    );
  }

  /**
   * A skipped change has no notices.
   */
  public function testNoNoticesWhenSkipped(): void {
    $this->assertSame([], $this->notices('mukurtu_remove_protocol_action', ['protocol' => (int) $this->p['A']->id()], $this->item(['A'], 'any')));
  }

  /**
   * The confirmation step lists notices, once per item.
   */
  public function testConfirmStepListsNotices(): void {
    $node = $this->item(['A', 'C'], 'any');
    $form = $this->confirmForm('mukurtu_change_sharing_action', ['sharing' => 'all'], [
      ['k1', 'en', 'node', $node->id()],
      ['k2', 'en', 'node', $node->id()],
    ]);

    $this->assertSame('Some people will lose access', (string) $form['mukurtu_protocol_loss']['heading']['#value']);
    $this->assertSame(
      ["A+C any: members who aren't in all of its protocols will lose access."],
      array_map('strval', $form['mukurtu_protocol_loss']['list']['#items']),
    );
    $this->assertArrayNotHasKey('#access', $form['actions']['submit']);
  }

  /**
   * Selecting every item in the view is blocked on the confirmation step.
   */
  public function testConfirmStepBlocksSelectAll(): void {
    $form = $this->confirmForm('mukurtu_change_sharing_action', ['sharing' => 'all'], [], TRUE);

    $this->assertFalse($form['actions']['submit']['#access']);
    $this->assertStringContainsString("can't be applied to every item", (string) $form['mukurtu_protocol_select_all']['#markup']);
  }

  /**
   * Other actions' confirmation steps are left alone.
   */
  public function testConfirmStepIgnoresOtherActions(): void {
    $form = $this->confirmForm('views_bulk_operations_delete_entity', [], [], TRUE);

    $this->assertArrayNotHasKey('mukurtu_protocol_select_all', $form);
    $this->assertArrayNotHasKey('#access', $form['actions']['submit']);
  }

  /**
   * Runs the confirmation form alter with the given VBO data.
   */
  protected function confirmForm(string $action_id, array $configuration, array $list, bool $exclude_mode = FALSE): array {
    $form = ['actions' => ['submit' => []]];
    $form_state = new FormState();
    $form_state->set('views_bulk_operations', [
      'action_id' => $action_id,
      'configuration' => $configuration,
      'list' => $list,
      'exclude_mode' => $exclude_mode,
    ]);
    (new BulkProtocolConfirmHooks())->confirmActionAlter($form, $form_state);
    return $form;
  }

  /**
   * Reloads a node from storage.
   */
  protected function reload(NodeInterface $node): NodeInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$node->id()]);
    return $storage->load($node->id());
  }

}
