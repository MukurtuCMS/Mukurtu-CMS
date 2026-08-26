<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\workflows\Entity\Workflow;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that archived protocol-gated content's view access is restricted
 * to genuine site-wide privilege, deliberately trumping protocol-based
 * access -- including a Protocol Steward's own OG-level "view any
 * unpublished content" grant, which every OTHER unpublished state (draft,
 * needs_review, etc.) still correctly honors.
 */
#[Group('mukurtu_protocol')]
class MukurtuProtocolNodeAccessControlHandlerArchivedTest extends KernelTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'field',
    'file',
    'image',
    'media',
    'mukurtu_protocol',
    'mukurtu_workflows',
    'node',
    'og',
    'options',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'workflows',
  ];

  /**
   * The content owner.
   */
  protected User $owner;

  /**
   * A protocol the owner and steward both belong to.
   */
  protected Protocol $protocol;

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
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');
    $this->installEntitySchema('og_membership');
    $this->installConfig(['og']);
    $this->installMukurtuWorkflowsConfig();

    node_access_rebuild();

    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');

    NodeType::create(['type' => 'thing', 'name' => 'Protocol Controlled Thing'])->save();

    // Trim the shipped bundle list down to the one bundle this test
    // actually creates -- the others (article, collection, etc.) don't
    // exist here, and calculateDependencies() requires every listed
    // bundle to have a real node type.
    $workflow = Workflow::load('mukurtu_default_content_workflow');
    $type_settings = $workflow->get('type_settings');
    $type_settings['entity_types']['node'] = [];
    $workflow->set('type_settings', $type_settings);
    $workflow->save();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', 'thing');

    $authenticated = Role::create(['id' => 'authenticated', 'label' => 'Authenticated user']);
    $authenticated->grantPermission('access content');
    // Matches the real config/install/user.role.authenticated.yml grants:
    // content_moderation denies 'update' outright ("No valid transitions
    // exist for given account") unless the account has at least one valid
    // transition, regardless of mukurtu_protocol's own access logic. Both
    // grants are needed so that gate is satisfied for draft (create_new_draft)
    // and archived (publish, per this workflow's from: [draft, published,
    // archived]) content, isolating the tests below to mukurtu_protocol's
    // own archived-state carve-out rather than this unrelated core gate.
    $authenticated->grantPermission('use mukurtu_default_content_workflow transition create_new_draft');
    $authenticated->grantPermission('use mukurtu_default_content_workflow transition publish');
    $authenticated->save();

    User::create(['name' => $this->randomMachineName()])->save();

    $this->owner = User::create(['name' => $this->randomMachineName()]);
    $this->owner->save();
    $this->container->get('current_user')->setAccount($this->owner);

    $community = Community::create(['name' => 'Community 1']);
    $community->save();
    $community->addMember($this->owner);

    $this->protocol = Protocol::create([
      'name' => 'Strict Protocol',
      'field_communities' => [$community->id()],
      'field_access_mode' => 'strict',
    ]);
    $this->protocol->save();

    $stewardRole = OgRole::create([
      'name' => 'protocol_steward',
      'label' => 'Steward',
      'permissions' => ['apply protocol', 'create thing node', 'view any unpublished content'],
    ]);
    $stewardRole->setGroupType('protocol');
    $stewardRole->setGroupBundle('protocol');
    $stewardRole->save();
    $this->protocol->addMember($this->owner, ['protocol_steward']);
  }

  /**
   * Installs only the workflow/settings config this test needs.
   */
  protected function installMukurtuWorkflowsConfig(): void {
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_workflows');
    $storage = new FileStorage($module_path . '/config/install');
    foreach ([
      'workflows.workflow.mukurtu_default_content_workflow',
      'workflows.workflow.mukurtu_editorial_workflow',
      'mukurtu_workflows.settings',
    ] as $name) {
      \Drupal::configFactory()->getEditable($name)->setData($storage->read($name))->save();
    }
  }

  /**
   * Creates a protocol-gated node in the given state, owned by $this->owner
   * unless a different $owner is given.
   */
  protected function createProtocolNode(string $moderation_state, ?User $owner = NULL): Node {
    $node = Node::create([
      'type' => 'thing',
      'title' => $this->randomString(),
      'uid' => ($owner ?? $this->owner)->id(),
      'moderation_state' => $moderation_state,
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    return $node;
  }

  /**
   * Creates a Contributor-equivalent user: a plain protocol member who can
   * create/update/delete their own content, but holds none of the
   * archive/restore transition permissions Managers and Stewards have.
   */
  protected function createContributor(): User {
    $contributorRole = OgRole::create([
      'name' => 'contributor',
      'label' => 'Contributor',
      'permissions' => [
        'apply protocol',
        'create thing node',
        'edit own thing content',
        'delete own thing content',
      ],
    ]);
    $contributorRole->setGroupType('protocol');
    $contributorRole->setGroupBundle('protocol');
    $contributorRole->save();

    $contributor = User::create(['name' => $this->randomMachineName()]);
    $contributor->save();
    $this->protocol->addMember($contributor, ['contributor']);
    return $contributor;
  }

  /**
   * A Protocol Steward's OG-level "view any unpublished content" grant is
   * NOT honored for archived content -- the whole point of this fix.
   */
  public function testStewardCannotViewArchivedContent(): void {
    $node = $this->createProtocolNode('archived');

    $steward = User::create(['name' => $this->randomMachineName()]);
    $steward->save();
    $this->protocol->addMember($steward, ['protocol_steward']);

    $this->assertFalse($node->access('view', $steward), 'Protocol-level grant does not reach archived content.');
  }

  /**
   * The same steward CAN still view draft content -- confirms the fix is
   * scoped to archived specifically, not a general regression across every
   * unpublished state.
   */
  public function testStewardCanStillViewDraftContent(): void {
    $node = $this->createProtocolNode('draft');

    $steward = User::create(['name' => $this->randomMachineName()]);
    $steward->save();
    $this->protocol->addMember($steward, ['protocol_steward']);

    $this->assertTrue($node->access('view', $steward), 'Protocol-level grant still reaches draft content.');
  }

  /**
   * The content's own owner loses view access once it's archived, unless
   * they also hold genuine site-wide privilege.
   */
  public function testOwnerCannotViewOwnArchivedContentWithoutSiteWidePrivilege(): void {
    $node = $this->createProtocolNode('archived');

    $this->assertFalse($node->access('view', $this->owner), 'Owner loses view access to their own archived content.');
  }

  /**
   * A site-wide privileged account (e.g. Mukurtu Manager, once granted
   * "view any unpublished content" site-wide) who is also a plain
   * protocol member (not a steward) can still view archived content --
   * the site-wide permission alone is what makes the difference, not
   * their (otherwise unprivileged) protocol role. Membership itself is
   * still required to reach protocol-gated content at all, matching the
   * existing, intentional design that Mukurtu Manager needs no reach
   * whatsoever into a protocol it isn't a member of at all (see
   * MukurtuManagerArchiveRestoreTest::testManagerCannotUpdateProtocolGatedContent).
   */
  public function testSiteWidePrivilegedUserCanViewArchivedContent(): void {
    $node = $this->createProtocolNode('archived');

    $manager_role = Role::create(['id' => 'manager', 'label' => 'Manager']);
    $manager_role->grantPermission('access content');
    $manager_role->grantPermission('view any unpublished content');
    $manager_role->save();
    $manager = User::create(['name' => $this->randomMachineName(), 'roles' => ['manager']]);
    $manager->save();
    // Og::addGroup() auto-creates a default 'member' role (empty
    // permissions) for every group bundle -- matching the real shipped
    // og.og_role.protocol-protocol-protocol_member.yml.
    $this->protocol->addMember($manager, ['member']);

    $this->assertTrue($node->access('view', $manager), 'Site-wide "view any unpublished content" reaches archived content, given at least plain protocol membership.');
  }

  /**
   * Anonymous users have no reach into archived content, regardless of
   * how the underlying node grants would otherwise resolve.
   */
  public function testAnonymousCannotViewArchivedContent(): void {
    $node = $this->createProtocolNode('archived');
    $anonymous = new AnonymousUserSession();

    $this->assertFalse($node->access('view', $anonymous), 'Anonymous has no reach into archived content.');
  }

  /**
   * A Contributor's ordinary "update own" protocol permission no longer
   * reaches update access on their own content once it's archived -- this
   * is the actual bug reported in PR #2036 QA: without this carve-out, a
   * Contributor could bulk-publish straight out of archived, because
   * ChangeModerationStateAction gates the 'publish' transition on real
   * 'update' access, which this handler was granting regardless of
   * moderation state.
   */
  public function testOwnerCannotUpdateOwnArchivedContentWithoutSiteWidePrivilege(): void {
    $contributor = $this->createContributor();
    $node = $this->createProtocolNode('archived', $contributor);

    $this->assertFalse($node->access('update', $contributor), 'Owner loses update access to their own archived content.');
  }

  /**
   * Same as above for 'delete' -- archiving is meant to fully take content
   * down until deliberately restored, so an owner shouldn't be able to
   * delete their own archived content either.
   */
  public function testOwnerCannotDeleteOwnArchivedContentWithoutSiteWidePrivilege(): void {
    $contributor = $this->createContributor();
    $node = $this->createProtocolNode('archived', $contributor);

    $this->assertFalse($node->access('delete', $contributor), 'Owner loses delete access to their own archived content.');
  }

  /**
   * The same Contributor CAN still update their own draft content --
   * confirms the carve-out is scoped to archived specifically.
   */
  public function testOwnerCanStillUpdateOwnDraftContent(): void {
    $contributor = $this->createContributor();
    $node = $this->createProtocolNode('draft', $contributor);

    $this->assertTrue($node->access('update', $contributor), 'Owner still has update access to their own draft content.');
  }

  /**
   * A site-wide privileged account (e.g. Mukurtu Manager) who also holds
   * real per-protocol update permission still gets update access to
   * archived content -- the site-wide permission alone doesn't grant
   * update, it only re-opens eligibility for the normal protocol-based
   * grant to apply, same as the 'view' carve-out's intent.
   */
  public function testSiteWidePrivilegedUserCanUpdateArchivedContent(): void {
    $manager_role = Role::create(['id' => 'manager', 'label' => 'Manager']);
    $manager_role->grantPermission('access content');
    $manager_role->grantPermission('view any unpublished content');
    $manager_role->save();
    $manager = User::create(['name' => $this->randomMachineName(), 'roles' => ['manager']]);
    $manager->save();

    $contributorRole = OgRole::create([
      'name' => 'contributor',
      'label' => 'Contributor',
      'permissions' => ['apply protocol', 'create thing node', 'edit own thing content'],
    ]);
    $contributorRole->setGroupType('protocol');
    $contributorRole->setGroupBundle('protocol');
    $contributorRole->save();
    $this->protocol->addMember($manager, ['contributor']);

    $node = $this->createProtocolNode('archived', $manager);

    $this->assertTrue($node->access('update', $manager), 'Site-wide "view any unpublished content" plus real per-protocol update permission reaches archived content.');
  }

}
