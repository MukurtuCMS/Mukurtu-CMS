<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\CommunityInterface;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the community delete form explains what blocks a delete.
 */
#[Group('mukurtu_protocol')]
class CommunityDeleteFormTest extends ProtocolAwareEntityTestBase {

  /**
   * The community under test.
   */
  protected CommunityInterface $community;

  /**
   * A user with the site-wide community delete permission.
   */
  protected UserInterface $deleter;

  /**
   * A user without delete permission.
   */
  protected UserInterface $viewer;

  /**
   * A user with the Mukurtu delete permission but not OG's.
   */
  protected UserInterface $partialDeleter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $role = Role::load('authenticated');
    $role->grantPermission('view published community entities');
    $role->save();

    // og_entity_access() also requires an OG permission to delete a group.
    $deleter_role = Role::create(['id' => 'deleter', 'label' => 'Deleter']);
    $deleter_role->grantPermission('delete community entities');
    $deleter_role->grantPermission('administer organic groups');
    $deleter_role->save();

    // Holds only the Mukurtu permission, so OG still forbids the delete.
    $partial_role = Role::create(['id' => 'partial', 'label' => 'Partial']);
    $partial_role->grantPermission('delete community entities');
    $partial_role->save();

    $this->community = Community::create([
      'name' => 'Community',
      'field_access_mode' => 'public',
    ]);
    $this->community->save();

    $this->deleter = $this->createUser([], NULL, FALSE, ['roles' => ['deleter']]);
    $this->viewer = $this->createUser();
    $this->partialDeleter = $this->createUser([], NULL, FALSE, ['roles' => ['partial']]);
  }

  /**
   * Creates a protocol in the community under test.
   */
  protected function createProtocol(string $name, string $access_mode): void {
    Protocol::create([
      'name' => $name,
      'field_communities' => [$this->community->id()],
      'field_access_mode' => $access_mode,
    ])->save();
  }

  /**
   * Builds the delete form for the community as the given user.
   */
  protected function buildDeleteForm(UserInterface $account): array {
    $this->setCurrentUser($account);
    $community = Community::load($this->community->id());
    return $this->container->get('entity.form_builder')->getForm($community, 'delete');
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
    $this->container->get('entity_type.manager')->getAccessControlHandler('community')->resetCache();
  }

  /**
   * Tests who can reach the delete form.
   */
  public function testDeleteFormAccess(): void {
    /** @var \Drupal\mukurtu_protocol\CommunityAccessControlHandler $handler */
    $handler = $this->container->get('entity_type.manager')->getAccessControlHandler('community');

    $this->assertTrue($this->community->access('delete', $this->deleter));
    $this->assertTrue($handler->deleteFormAccess($this->community, $this->deleter)->isAllowed());
    $this->assertFalse($handler->deleteFormAccess($this->community, $this->viewer)->isAllowed());
    $this->assertFalse($this->community->access('delete', $this->partialDeleter));
    $this->assertFalse($handler->deleteFormAccess($this->community, $this->partialDeleter)->isAllowed());

    $this->createProtocol('Open protocol', 'open');
    $this->resetAccessCache();
    $community = Community::load($this->community->id());
    $this->assertFalse($community->access('delete', $this->deleter));
    $this->assertTrue($handler->deleteFormAccess($community, $this->deleter)->isAllowed());
    $this->assertFalse($handler->deleteFormAccess($community, $this->viewer)->isAllowed());
    $this->assertFalse($handler->deleteFormAccess($community, $this->partialDeleter)->isAllowed());

    $url = $community->toUrl('delete-form');
    $this->assertTrue($url->access($this->deleter));
    $this->assertFalse($url->access($this->viewer));
  }

  /**
   * Tests that an empty community gets the normal confirmation form.
   */
  public function testEmptyCommunityShowsConfirmation(): void {
    $form = $this->buildDeleteForm($this->deleter);
    $this->assertArrayHasKey('submit', $form['actions']);
    $this->assertArrayNotHasKey('protocols_warning', $form);
  }

  /**
   * Tests the explanation for protocols in the community.
   */
  public function testDeleteFormListsProtocols(): void {
    $this->createProtocol('Open protocol', 'open');
    $this->createProtocol('Strict protocol', 'strict');

    $form = $this->buildDeleteForm($this->deleter);

    $this->assertArrayNotHasKey('submit', $form['actions']);
    $this->assertArrayHasKey('cancel', $form['actions']);
    $linked = array_map(fn($item) => (string) $item['#context']['link']['#title'], $form['protocols']['list']['#items']);
    $this->assertEquals(['Open protocol'], $linked);
    $this->assertArrayNotHasKey('children_warning', $form);

    $output = $this->renderPlain($form);
    $this->assertStringContainsString("This community can't be deleted because 2 cultural protocols still belong to it.", $output);
    $this->assertStringContainsString('Delete these protocols, or remove this community from them, first.', $output);
    $this->assertStringContainsString('Cultural protocols in this community', $output);
    $this->assertStringContainsString("1 of these cultural protocols is one you don't have access to. Contact a community manager to update it.", $output);
    $this->assertStringNotContainsString('Strict protocol', $output);
  }

  /**
   * Tests the explanation for child communities.
   */
  public function testDeleteFormListsChildCommunities(): void {
    $child = Community::create([
      'name' => 'Child community',
      'field_access_mode' => 'public',
    ]);
    $child->save();
    // Community hierarchy is stored in config, not on the entities.
    $this->config('mukurtu_protocol.community_organization')
      ->set('organization', [
        $this->community->id() => ['parent' => 0, 'weight' => 0],
        $child->id() => ['parent' => (int) $this->community->id(), 'weight' => 0],
      ])
      ->save();

    $form = $this->buildDeleteForm($this->deleter);

    $this->assertArrayNotHasKey('submit', $form['actions']);
    $this->assertArrayNotHasKey('protocols_warning', $form);
    $linked = array_map(fn($item) => (string) $item['#context']['link']['#title'], $form['children']['list']['#items']);
    $this->assertEquals(['Child community'], $linked);

    $output = $this->renderPlain($form);
    $this->assertStringContainsString("This community can't be deleted because it has 1 child community.", $output);
    $this->assertStringContainsString('Remove the child communities first.', $output);
    $this->assertStringContainsString('Child communities', $output);
  }

  /**
   * Tests that submitting the form doesn't delete a blocked community.
   */
  public function testSubmitDoesNotDeleteBlockedCommunity(): void {
    $this->setCurrentUser($this->deleter);
    $form_object = $this->container->get('entity_type.manager')->getFormObject('community', 'delete');
    $form_object->setEntity($this->community);

    // A protocol is added after the form was loaded.
    $form_state = new FormState();
    $this->container->get('form_builder')->buildForm($form_object, $form_state);
    $this->createProtocol('Open protocol', 'open');
    $this->resetAccessCache();

    $form_state = (new FormState())->setValues(['op' => 'Delete']);
    $this->container->get('form_builder')->submitForm($form_object, $form_state);

    $this->assertNotNull(Community::load($this->community->id()));
  }

  /**
   * Tests the notice on the community edit form.
   */
  public function testEditFormNotice(): void {
    $this->setCurrentUser($this->deleter);
    $form = $this->container->get('entity.form_builder')->getForm(Community::load($this->community->id()), 'edit');
    $this->assertArrayNotHasKey('delete_blocked_notice', $form);

    $this->createProtocol('Open protocol', 'open');
    $this->resetAccessCache();

    $form = $this->container->get('entity.form_builder')->getForm(Community::load($this->community->id()), 'edit');
    $this->assertArrayHasKey('delete_blocked_notice', $form);
    $output = $this->renderPlain($form['delete_blocked_notice']);
    $this->assertStringContainsString("This community can't be deleted because it still has cultural protocols or child communities.", $output);
    $this->assertStringContainsString('See why', $output);
    $this->assertStringContainsString($this->community->toUrl('delete-form')->toString(), $output);
  }

}
