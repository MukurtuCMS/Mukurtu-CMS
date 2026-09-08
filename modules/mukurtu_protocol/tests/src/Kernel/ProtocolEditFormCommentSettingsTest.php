<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\mukurtu_protocol\Form\ProtocolForm;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests the comment settings section folded into the protocol edit form.
 *
 * @see mukurtu_protocol_form_protocol_edit_form_alter()
 * @see mukurtu_protocol_comment_settings_entity_builder()
 */
#[Group('mukurtu_protocol')]
class ProtocolEditFormCommentSettingsTest extends KernelTestBase {

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
  ];

  /**
   * @var \Drupal\mukurtu_protocol\Entity\ProtocolInterface
   */
  protected $protocol;

  /**
   * Steward user, holds the 'administer comments' OG permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $stewardUser;

  /**
   * Plain member user, does not hold 'administer comments'.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $memberUser;

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

    Role::create(['id' => 'anonymous', 'label' => 'anonymous'])->save();
    Role::create(['id' => 'authenticated', 'label' => 'authenticated'])->save();

    // Sacrificial uid 1 so the real test users below don't inherit the
    // Drupal admin permission bypass.
    User::create(['name' => $this->randomString()])->save();

    $steward = OgRole::create([
      'name' => 'protocol_steward',
      'label' => 'Protocol Steward',
      'permissions' => ['administer comments', 'update group'],
    ]);
    $steward->setGroupType('protocol');
    $steward->setGroupBundle('protocol');
    $steward->save();

    $member = OgRole::create([
      'name' => 'protocol_member',
      'label' => 'Protocol Member',
      'permissions' => [],
    ]);
    $member->setGroupType('protocol');
    $member->setGroupBundle('protocol');
    $member->save();

    $owner = User::create(['name' => $this->randomString()]);
    $owner->save();

    $this->protocol = Protocol::create([
      'name' => $this->randomString(),
      'status' => TRUE,
      'uid' => $owner->id(),
    ]);
    $this->protocol->save();

    $this->stewardUser = User::create(['name' => $this->randomString()]);
    $this->stewardUser->save();
    $this->protocol->addMember($this->stewardUser, ['protocol_steward']);

    $this->memberUser = User::create(['name' => $this->randomString()]);
    $this->memberUser->save();
    $this->protocol->addMember($this->memberUser, ['protocol_member']);
  }

  /**
   * Builds a ProtocolForm form object/state pair bound to $this->protocol.
   */
  protected function createFormState(): FormState {
    $form_object = ProtocolForm::create($this->container);
    $form_object->setEntity($this->protocol);
    $form_state = new FormState();
    $form_state->setFormObject($form_object);
    return $form_state;
  }

  /**
   * Users with 'administer comments' should see the new section.
   */
  public function testSectionVisibleForUserWithAdministerComments(): void {
    \Drupal::currentUser()->setAccount($this->stewardUser);

    $form = [];
    $form_state = $this->createFormState();
    mukurtu_protocol_form_protocol_edit_form_alter($form, $form_state);

    $this->assertArrayHasKey('comment_settings', $form);
    $this->assertSame('details', $form['comment_settings']['#type']);
  }

  /**
   * Users without 'administer comments' should not see the new section.
   */
  public function testSectionHiddenForUserWithoutAdministerComments(): void {
    \Drupal::currentUser()->setAccount($this->memberUser);

    $form = [];
    $form_state = $this->createFormState();
    mukurtu_protocol_form_protocol_edit_form_alter($form, $form_state);

    $this->assertArrayNotHasKey('comment_settings', $form);
    $this->assertArrayNotHasKey('#entity_builders', $form);
  }

  /**
   * Submitted values persist onto the entity, with post access constrained
   * to a subset of view access.
   */
  public function testEntityBuilderPersistsSettingsAndConstrainsPostAccess(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValues([
      'comments_enabled' => 1,
      'comments_require_approval' => 1,
      'comment_view_access' => [
        'anonymous' => 0,
        'authenticated' => 'authenticated',
        'protocol_member' => 0,
      ],
      'comment_post_access' => [
        'anonymous' => 0,
        'authenticated' => 'authenticated',
        // Requested but not in view access above - must be stripped.
        'protocol_member' => 'protocol_member',
      ],
    ]);

    mukurtu_protocol_comment_settings_entity_builder('protocol', $this->protocol, $form, $form_state);
    $this->protocol->save();

    $reloaded = \Drupal::entityTypeManager()->getStorage('protocol')->loadUnchanged($this->protocol->id());
    $this->assertTrue($reloaded->getCommentStatus());
    $this->assertTrue($reloaded->getCommentRequireApproval());
    $this->assertEqualsCanonicalizing(['authenticated'], $reloaded->getCommentViewAccess());
    $this->assertEqualsCanonicalizing(['authenticated'], $reloaded->getCommentPostAccess());
  }

  /**
   * Site-wide anonymous restrictions are enforced as a ceiling even when the
   * protocol form submission requests 'anonymous' access.
   */
  public function testEntityBuilderStripsAnonymousWhenSiteDoesNotAllow(): void {
    // Default anonymous role has neither 'access comments' nor
    // 'post comments', so both should be stripped.
    $form = [];
    $form_state = new FormState();
    $form_state->setValues([
      'comments_enabled' => 1,
      'comments_require_approval' => 0,
      'comment_view_access' => ['anonymous' => 'anonymous'],
      'comment_post_access' => ['anonymous' => 'anonymous'],
    ]);

    mukurtu_protocol_comment_settings_entity_builder('protocol', $this->protocol, $form, $form_state);
    $this->protocol->save();

    $reloaded = \Drupal::entityTypeManager()->getStorage('protocol')->loadUnchanged($this->protocol->id());
    $this->assertSame([], $reloaded->getCommentViewAccess());
    $this->assertSame([], $reloaded->getCommentPostAccess());
  }

  /**
   * A site-wide approval requirement is a floor the protocol cannot lower.
   */
  public function testEntityBuilderForcesApprovalWhenSiteRequiresItSitewide(): void {
    $this->config('mukurtu_protocol.comment_settings')
      ->set('site_comments_require_approval', TRUE)
      ->save();

    $form = [];
    $form_state = new FormState();
    $form_state->setValues([
      'comments_enabled' => 1,
      // Form submits 'disabled', but site-wide requires approval.
      'comments_require_approval' => 0,
      'comment_view_access' => [],
      'comment_post_access' => [],
    ]);

    mukurtu_protocol_comment_settings_entity_builder('protocol', $this->protocol, $form, $form_state);
    $this->protocol->save();

    $reloaded = \Drupal::entityTypeManager()->getStorage('protocol')->loadUnchanged($this->protocol->id());
    $this->assertTrue($reloaded->getCommentRequireApproval());
  }

  /**
   * The standalone comment-settings route/form are fully removed.
   */
  public function testStandaloneCommentSettingsRouteAndFormAreRemoved(): void {
    $this->expectException(\Symfony\Component\Routing\Exception\RouteNotFoundException::class);
    \Drupal::service('router.route_provider')->getRouteByName('mukurtu_protocol.manage_protocol_comment_settings');
  }

  /**
   * The standalone comment-settings form class no longer exists.
   */
  public function testStandaloneCommentSettingsFormClassRemoved(): void {
    $this->assertFalse(class_exists('Drupal\mukurtu_protocol\Form\ProtocolCommentSettingsForm'));
  }

}
