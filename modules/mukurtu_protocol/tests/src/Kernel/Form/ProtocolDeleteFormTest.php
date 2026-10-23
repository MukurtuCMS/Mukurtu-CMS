<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\media\Entity\Media;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\mukurtu_protocol\Entity\ProtocolInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\og\Entity\OgRole;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the protocol delete form explains what blocks a delete.
 */
#[Group('mukurtu_protocol')]
class ProtocolDeleteFormTest extends ProtocolAwareEntityTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media_test_source',
  ];

  /**
   * The community that owns the protocols.
   */
  protected Community $community;

  /**
   * The open protocol under test.
   */
  protected ProtocolInterface $protocol;

  /**
   * A strict protocol the manager isn't a member of.
   */
  protected ProtocolInterface $strictProtocol;

  /**
   * A community manager allowed to delete protocols.
   */
  protected UserInterface $manager;

  /**
   * A community member without delete permission.
   */
  protected UserInterface $member;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installSchema('file', 'file_usage');
    $this->installEntitySchema('media');
    $this->installConfig(['media']);
    $this->createMediaType('test', ['id' => 'test']);

    $role = OgRole::create([
      'name' => 'community_manager',
      'label' => 'Community Manager',
      'permissions' => [
        'delete any protocol protocol',
        'update any protocol protocol',
      ],
    ]);
    $role->setGroupType('community');
    $role->setGroupBundle('community');
    $role->save();

    $this->community = Community::create(['name' => 'Community']);
    $this->community->save();

    $this->protocol = Protocol::create([
      'name' => 'Open protocol',
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'open',
    ]);
    $this->protocol->save();

    $this->strictProtocol = Protocol::create([
      'name' => 'Strict protocol',
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'strict',
    ]);
    $this->strictProtocol->save();

    $this->manager = $this->createUser();
    $this->community->addMember($this->manager, ['community_manager']);

    $this->member = $this->createUser();
    $this->community->addMember($this->member);
  }

  /**
   * Creates a node that uses the given protocols.
   */
  protected function createProtocolNode(array $protocols, string $sharing = 'any', bool $published = TRUE): NodeInterface {
    // Save as uid 1, since protocols the current user can't apply are
    // stripped on save.
    $current = $this->container->get('current_user')->getAccount();
    $this->setCurrentUser($this->currentUser);
    $node = Node::create([
      'title' => $this->randomMachineName(),
      'type' => 'protocol_aware_content',
      'status' => $published,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting($sharing);
    $node->setProtocols(array_map(fn($p) => $p->id(), $protocols));
    $node->save();
    $this->setCurrentUser($current);
    return $node;
  }

  /**
   * Builds the delete form for the protocol as the given user.
   */
  protected function buildDeleteForm(UserInterface $account): array {
    $this->setCurrentUser($account);
    $protocol = Protocol::load($this->protocol->id());
    return $this->container->get('entity.form_builder')->getForm($protocol, 'delete');
  }

  /**
   * Renders a render array and decodes HTML entities.
   */
  protected function renderPlain(array $build): string {
    $output = (string) $this->container->get('renderer')->renderInIsolation($build);
    return html_entity_decode($output, ENT_QUOTES);
  }

  /**
   * Resets the static access caches.
   */
  protected function resetAccessCache(): void {
    $this->container->get('entity_type.manager')->getAccessControlHandler('protocol')->resetCache();
  }

  /**
   * Tests who can reach the delete form.
   */
  public function testDeleteFormAccess(): void {
    /** @var \Drupal\mukurtu_protocol\ProtocolAccessControlHandler $handler */
    $handler = $this->container->get('entity_type.manager')->getAccessControlHandler('protocol');

    // Unused: the manager can delete, the member can't.
    $this->assertTrue($this->protocol->access('delete', $this->manager));
    $this->assertTrue($handler->deleteFormAccess($this->protocol, $this->manager)->isAllowed());
    $this->assertFalse($this->protocol->access('delete', $this->member));
    $this->assertFalse($handler->deleteFormAccess($this->protocol, $this->member)->isAllowed());

    // In use: delete stays forbidden, but the manager can still reach the
    // form that explains why.
    $this->createProtocolNode([$this->protocol]);
    $this->resetAccessCache();
    $this->assertFalse($this->protocol->access('delete', $this->manager));
    $this->assertTrue($handler->deleteFormAccess($this->protocol, $this->manager)->isAllowed());
    $this->assertFalse($this->protocol->access('delete', $this->member));
    $this->assertFalse($handler->deleteFormAccess($this->protocol, $this->member)->isAllowed());

    // The route uses the same check.
    $url = $this->protocol->toUrl('delete-form');
    $this->assertTrue($url->access($this->manager));
    $this->assertFalse($url->access($this->member));
  }

  /**
   * Tests that an unused protocol gets the normal confirmation form.
   */
  public function testUnusedProtocolShowsConfirmation(): void {
    $form = $this->buildDeleteForm($this->manager);
    $this->assertArrayHasKey('submit', $form['actions']);
    $this->assertArrayNotHasKey('warning', $form);
  }

  /**
   * Tests the explanation for viewable and restricted items.
   */
  public function testDeleteFormListsBlockers(): void {
    $viewable_node = $this->createProtocolNode([$this->protocol]);
    $media = Media::create([
      'bundle' => 'test',
      'name' => 'Viewable media',
      'uid' => $this->currentUser->id(),
      'status' => TRUE,
    ]);
    $media->setSharingSetting('any');
    $media->setProtocols([$this->protocol->id()]);
    $media->save();
    // Needs both protocols, and the manager isn't in the strict one.
    $restricted_node = $this->createProtocolNode([$this->protocol, $this->strictProtocol], 'all');
    // Drafts count too, since inUse() ignores publishing status.
    $draft = $this->createProtocolNode([$this->protocol], 'any', FALSE);

    $form = $this->buildDeleteForm($this->manager);

    $this->assertArrayNotHasKey('submit', $form['actions']);
    $this->assertArrayHasKey('cancel', $form['actions']);

    $linked = array_map(fn($item) => $item['link']['#title'], $form['blockers']['list']['#items']);
    $this->assertEqualsCanonicalizing([$viewable_node->label(), $media->label(), $draft->label()], $linked);
    $this->assertNotContains($restricted_node->label(), $linked);
    $this->assertArrayNotHasKey('more', $form['blockers']);

    $output = $this->renderPlain($form);
    $this->assertStringContainsString("This cultural protocol can't be deleted because 4 items still use it.", $output);
    $this->assertStringContainsString('Remove the protocol from these items first.', $output);
    $this->assertStringContainsString('Items using this protocol', $output);
    $this->assertStringContainsString("1 of these items is in a cultural protocol you don't have access to.", $output);
    $this->assertStringContainsString('Contact a protocol steward to update them.', $output);
    $this->assertStringNotContainsString($restricted_node->label(), $output);
  }

  /**
   * Tests that only the first 20 viewable items are linked.
   */
  public function testDeleteFormCapsLinks(): void {
    for ($i = 0; $i < 25; $i++) {
      $this->createProtocolNode([$this->protocol]);
    }

    $form = $this->buildDeleteForm($this->manager);

    $this->assertCount(20, $form['blockers']['list']['#items']);
    $this->assertEquals('And 5 more items you can view.', (string) $form['blockers']['more']['#value']);
    $this->assertArrayNotHasKey('restricted', $form);
  }

  /**
   * Tests that submitting the form doesn't delete a protocol in use.
   */
  public function testSubmitDoesNotDeleteProtocolInUse(): void {
    $this->setCurrentUser($this->manager);
    $form_object = $this->container->get('entity_type.manager')->getFormObject('protocol', 'delete');
    $form_object->setEntity($this->protocol);

    // Content starts using the protocol after the form was loaded.
    $form_state = new FormState();
    $this->container->get('form_builder')->buildForm($form_object, $form_state);
    $this->createProtocolNode([$this->protocol]);

    $form_state = (new FormState())->setValues(['op' => 'Delete']);
    $this->container->get('form_builder')->submitForm($form_object, $form_state);

    $this->assertNotNull(Protocol::load($this->protocol->id()));
  }

  /**
   * Tests the notice on the protocol edit form.
   */
  public function testEditFormNotice(): void {
    // No notice while the protocol is unused.
    $this->setCurrentUser($this->manager);
    $form = $this->container->get('entity.form_builder')->getForm(Protocol::load($this->protocol->id()), 'edit');
    $this->assertArrayNotHasKey('delete_blocked_notice', $form);

    $this->createProtocolNode([$this->protocol]);
    $this->resetAccessCache();

    $form = $this->container->get('entity.form_builder')->getForm(Protocol::load($this->protocol->id()), 'edit');
    $this->assertArrayHasKey('delete_blocked_notice', $form);
    $output = $this->renderPlain($form['delete_blocked_notice']);
    $this->assertStringContainsString("This cultural protocol can't be deleted because 1 item still uses it.", $output);
    $this->assertStringContainsString('See which items', $output);
    $this->assertStringContainsString($this->protocol->toUrl('delete-form')->toString(), $output);

    // A protocol steward can edit but not delete, so gets no notice.
    $steward = $this->createUser();
    $this->protocol->addMember($steward, ['protocol_steward']);
    $this->setCurrentUser($steward);
    $form = $this->container->get('entity.form_builder')->getForm(Protocol::load($this->protocol->id()), 'edit');
    $this->assertArrayNotHasKey('delete_blocked_notice', $form);
  }

}
