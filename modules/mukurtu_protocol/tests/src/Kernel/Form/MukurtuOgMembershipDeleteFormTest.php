<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\mukurtu_protocol\Form\MukurtuOgMembershipDeleteForm;
use Drupal\mukurtu_protocol\Hook\MembershipPageLabelHooks;
use Drupal\og\Entity\OgMembership;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\og\OgMembershipInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Tests that removing a single member talks about removal, not deletion.
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2009
 */
#[Group('mukurtu_protocol')]
#[CoversClass(MukurtuOgMembershipDeleteForm::class)]
#[CoversClass(MembershipPageLabelHooks::class)]
class MukurtuOgMembershipDeleteFormTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'content_moderation',
    'workflows',
    'field',
    'file',
    'filter',
    'geofield',
    'image',
    'leaflet',
    'node',
    'node_access_test',
    'media',
    'og',
    'options',
    'system',
    'text',
    'taxonomy',
    'user',
    'mukurtu_core',
    'mukurtu_protocol',
    'views',
    'views_bulk_operations',
  ];

  /**
   * The community members are removed from.
   */
  protected Community $community;

  /**
   * A protocol within the community.
   */
  protected Protocol $protocol;

  /**
   * A member of both the community and the protocol.
   */
  protected User $member;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['og', 'filter', 'system']);
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('workflow');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');
    $this->installSchema('system', 'sequences');

    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');

    Role::create(['id' => 'authenticated', 'label' => 'authenticated'])->save();

    foreach (['community', 'protocol'] as $group_type) {
      $role = OgRole::create([
        'name' => "{$group_type}_member",
        'label' => 'Member',
        'permissions' => [],
      ]);
      $role->setGroupType($group_type);
      $role->setGroupBundle($group_type);
      $role->save();
    }

    // The first user created is uid 1, which bypasses access checks, so it is
    // spent on the group owner rather than on anybody under test.
    $owner = User::create(['name' => 'group_owner']);
    $owner->save();
    $this->setCurrentUser($owner);

    $this->community = Community::create([
      'name' => 'Test community',
      'status' => TRUE,
      'user_id' => $owner->id(),
    ]);
    $this->community->save();

    $this->protocol = Protocol::create([
      'name' => 'Test protocol',
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'strict',
      'user_id' => $owner->id(),
    ]);
    $this->protocol->save();

    $this->member = User::create(['name' => 'removable_member']);
    $this->member->save();
    $this->community->addMember($this->member, ['community_member']);
    $this->protocol->addMember($this->member, ['protocol_member']);
  }

  /**
   * Returns the delete form object for a membership.
   */
  protected function deleteForm(OgMembershipInterface $membership): MukurtuOgMembershipDeleteForm {
    $form_object = \Drupal::entityTypeManager()->getFormObject('og_membership', 'delete');
    $this->assertInstanceOf(MukurtuOgMembershipDeleteForm::class, $form_object);
    $form_object->setEntity($membership);
    return $form_object;
  }

  /**
   * Tests the wording on the confirm form for a protocol membership.
   */
  public function testProtocolWording(): void {
    $form = $this->deleteForm(Og::getMembership($this->protocol, $this->member));

    $this->assertSame('Remove <em class="placeholder">removable_member</em> from <em class="placeholder">Test protocol</em>?', (string) $form->getQuestion());
    $description = (string) $form->getDescription();
    $this->assertStringContainsString('no longer see content shared with that protocol', $description);
    $this->assertStringNotContainsString('cannot be undone', $description);
    $this->assertSame('Remove', (string) $form->getConfirmText());
  }

  /**
   * Tests the wording on the confirm form for a community membership.
   */
  public function testCommunityWording(): void {
    $form = $this->deleteForm(Og::getMembership($this->community, $this->member));

    $this->assertSame('Remove <em class="placeholder">removable_member</em> from <em class="placeholder">Test community</em>?', (string) $form->getQuestion());
    $this->assertStringContainsString('no longer see content shared with that community', (string) $form->getDescription());
    $this->assertSame('Remove', (string) $form->getConfirmText());
  }

  /**
   * Tests that submitting the form removes the member and says so.
   */
  public function testSubmitRemovesMember(): void {
    $membership = Og::getMembership($this->protocol, $this->member);
    $form_object = $this->deleteForm($membership);

    $form_state = (new FormState())->setValues(['op' => 'Remove']);
    \Drupal::formBuilder()->submitForm($form_object, $form_state);

    $this->assertEmpty($form_state->getErrors());
    $this->assertNull(OgMembership::load($membership->id()), 'The membership is gone.');
    $messages = \Drupal::messenger()->messagesByType('status');
    $this->assertSame('Removed <em class="placeholder">removable_member</em> from <em class="placeholder">Test protocol</em>.', (string) reset($messages));
  }

  /**
   * Tests that the membership page's tab says "Remove", not "Delete".
   */
  public function testRemoveTabLabel(): void {
    $definition = \Drupal::service('plugin.manager.menu.local_task')
      ->getDefinition('entity.og_membership.delete_form');
    $this->assertSame('Remove', (string) $definition['title']);
  }

  /**
   * Tests that the breadcrumb preprocess runs after the theme's own.
   */
  public function testBreadcrumbPreprocessRunsLast(): void {
    $this->container->get('theme_installer')->install(['claro']);
    $this->config('system.theme')->set('default', 'claro')->save();
    $preprocess = \Drupal::service('theme.registry')->get()['breadcrumb']['preprocess functions'];
    $this->assertSame(MembershipPageLabelHooks::class . '::preprocessBreadcrumb', end($preprocess));
  }

  /**
   * Tests that the unlinked operation item Gin adds is relabelled.
   */
  public function testBreadcrumbRelabel(): void {
    $gin_breadcrumb = [
      ['text' => 'Back to site', 'url' => '/'],
      ['text' => 'Members', 'url' => '/group/protocol/1/admin/members'],
      ['text' => 'Delete Cultural Protocol', 'url' => ''],
    ];

    $this->setRouteName('entity.og_membership.delete_form');
    $variables = ['breadcrumb' => $gin_breadcrumb];
    MembershipPageLabelHooks::preprocessBreadcrumb($variables);
    $this->assertSame('Remove member', (string) $variables['breadcrumb'][2]['text']);
    $this->assertSame('Members', $variables['breadcrumb'][1]['text'], 'Linked items are left alone.');

    $this->setRouteName('entity.og_membership.canonical');
    $variables = ['breadcrumb' => $gin_breadcrumb];
    MembershipPageLabelHooks::preprocessBreadcrumb($variables);
    $this->assertSame('Manage roles', (string) $variables['breadcrumb'][2]['text']);

    // Without Gin's unlinked item, the last item is a link and is kept.
    $variables = ['breadcrumb' => array_slice($gin_breadcrumb, 0, 2)];
    MembershipPageLabelHooks::preprocessBreadcrumb($variables);
    $this->assertSame('Members', $variables['breadcrumb'][1]['text']);

    // Other routes are not touched.
    $this->setRouteName('entity.protocol.delete_form');
    $variables = ['breadcrumb' => $gin_breadcrumb];
    MembershipPageLabelHooks::preprocessBreadcrumb($variables);
    $this->assertSame('Delete Cultural Protocol', $variables['breadcrumb'][2]['text']);
  }

  /**
   * Makes the current route match report the given route name.
   */
  protected function setRouteName(string $route_name): void {
    $request = Request::create('/');
    $request->setSession($this->container->get('request_stack')->getCurrentRequest()->getSession());
    $request->attributes->set('_route', $route_name);
    $request->attributes->set('_route_object', new Route('/'));
    $this->container->get('request_stack')->push($request);
    $this->container->get('current_route_match')->resetRouteMatch();
  }

}
