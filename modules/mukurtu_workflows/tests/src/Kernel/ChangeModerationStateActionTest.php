<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_workflows\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Plugin\Action\ChangeModerationStateAction;
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
 * Tests the mukurtu_change_moderation_state_action plugin: it must respect
 * both the transition-legality check (getValidTransitions()) AND the real,
 * OG-protocol-scoped node access check -- neither alone is sufficient.
 */
#[Group('mukurtu_workflows')]
class ChangeModerationStateActionTest extends KernelTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'field',
    'file',
    'geofield',
    'image',
    'leaflet',
    'media',
    'mukurtu_core',
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
   * The Mukurtu Manager test user.
   */
  protected User $manager;

  /**
   * A protocol the manager is not a member of.
   */
  protected Protocol $protocol;

  /**
   * A user to own test content.
   */
  protected User $owner;

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

    // article is structural (excluded from mukurtu_protocol's catch-all
    // bundle class); thing picks up MukurtuNode::class and is
    // protocol-gated; landing_page is created but never attached to any
    // workflow, matching this session's earlier redesign, so it stands in
    // for a non-moderated bundle.
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'thing', 'name' => 'Protocol Controlled Thing'])->save();
    NodeType::create(['type' => 'landing_page', 'name' => 'Landing Page'])->save();

    // Trim the shipped bundle list down to the bundles this test actually
    // creates -- the others (collection, person, etc.) don't exist here, and
    // calculateDependencies() requires every listed bundle to have a real
    // node type.
    $workflow = Workflow::load('mukurtu_default_content_workflow');
    $type_settings = $workflow->get('type_settings');
    $type_settings['entity_types']['node'] = ['article'];
    $workflow->set('type_settings', $type_settings);
    $workflow->save();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', 'thing');

    // Baseline permission every authenticated user has on a real site.
    $authenticated = Role::create(['id' => 'authenticated', 'label' => 'Authenticated user']);
    $authenticated->grantPermission('access content');
    // Matches the real config/install/user.role.authenticated.yml grant
    // that makes 'publish' a technically-valid transition for everyone,
    // including straight out of 'archived' -- see
    // testAccessDeniedForContributorPublishingOwnArchivedContent().
    $authenticated->grantPermission('use mukurtu_default_content_workflow transition publish');
    $authenticated->save();

    $role = Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager']);
    $role->grantPermission('edit any article content');
    $role->grantPermission('use mukurtu_default_content_workflow transition archive');
    $role->grantPermission('use mukurtu_default_content_workflow transition restore');
    $role->grantPermission('use mukurtu_default_content_workflow transition publish');
    $role->grantPermission('use mukurtu_default_content_workflow transition create_new_draft');
    $role->save();

    // Burn uid 1 on a throwaway user -- the Drupal superuser bypasses all
    // permission checks unconditionally, and it would otherwise silently
    // land on the first user created below, masking real access denials.
    User::create(['name' => $this->randomMachineName()])->save();

    $this->manager = User::create(['name' => $this->randomMachineName(), 'roles' => ['mukurtu_manager']]);
    $this->manager->save();

    $this->owner = User::create(['name' => $this->randomMachineName()]);
    $this->owner->save();

    // The acting (current) user during node save must have "apply protocol"
    // on the protocol being set, or CulturalProtocolItem::preSave() silently
    // strips protocols the current user isn't permitted to apply.
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

    $contributorRole = OgRole::create([
      'name' => 'contributor',
      'label' => 'Contributor',
      'permissions' => ['apply protocol', 'create thing node'],
    ]);
    $contributorRole->setGroupType('protocol');
    $contributorRole->setGroupBundle('protocol');
    $contributorRole->save();
    $this->protocol->addMember($this->owner, ['contributor']);
  }

  /**
   * Installs only the workflow/settings config this test needs.
   *
   * Avoids installConfig(['mukurtu_workflows']), which would also install
   * the module's Views config (mukurtu_workflow_overview) and pull in
   * dependencies (filter, views field plugins) this test doesn't enable.
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
   * Creates a moderated node in the given state, owned by $this->owner.
   */
  protected function createModeratedNode(string $bundle, string $moderation_state): Node {
    $node = Node::create([
      'type' => $bundle,
      'title' => $this->randomString(),
      'uid' => $this->owner->id(),
      'moderation_state' => $moderation_state,
    ]);
    $node->save();
    return $node;
  }

  /**
   * Instantiates the action plugin, configured for the given target state.
   */
  protected function createAction(string $target_state): ChangeModerationStateAction {
    return ChangeModerationStateAction::create(
      $this->container,
      ['target_state' => $target_state],
      'mukurtu_change_moderation_state_action',
      [],
    );
  }

  /**
   * Access is allowed for a valid transition on structural content the
   * manager can edit.
   */
  public function testAccessAllowedForValidTransitionOnStructuralNode(): void {
    $published = $this->createModeratedNode('article', 'published');
    $action = $this->createAction('archived');
    $this->assertTrue($action->access($published, $this->manager));
  }

  /**
   * Access is denied for a protocol-gated node the manager isn't an OG
   * member of, even though the target state IS technically valid per
   * getValidTransitions() alone -- the OG-scoped node access check is the
   * real boundary. This is the regression guard for the dual-check design.
   */
  public function testAccessDeniedForProtocolGatedNodeDespiteValidTransition(): void {
    // Give the manager the site-wide editorial/default archive permission
    // on 'thing' too, so getValidTransitions() alone WOULD say yes --
    // proving it's node access, not transition legality, doing the real
    // denying here.
    $role = Role::load('mukurtu_manager');
    $role->grantPermission('use mukurtu_default_content_workflow transition archive');
    $role->save();

    $node = Node::create([
      'type' => 'thing',
      'title' => $this->randomString(),
      'uid' => $this->owner->id(),
      'moderation_state' => 'published',
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();

    $transitions = \Drupal::service('content_moderation.state_transition_validation')
      ->getValidTransitions($node, $this->manager);
    $this->assertArrayHasKey('archive', $transitions, 'Sanity check: transition-permission alone says yes.');

    $action = $this->createAction('archived');
    $this->assertFalse($action->access($node, $this->manager));
  }

  /**
   * A plain OG member (empty permissions, matching the real
   * og.og_role.protocol-protocol-protocol_member.yml) can archive
   * protocol-gated content they can view, holding only the site-wide
   * transition permission -- archive/restore are gated on 'view', not
   * 'update', since they're pure moderation decisions.
   */
  public function testAccessAllowedForPlainMemberOnArchiveTransition(): void {
    // Og::addGroup() already auto-creates a default 'member' role (empty
    // permissions) for every group bundle -- matching the real shipped
    // og.og_role.protocol-protocol-protocol_member.yml's empty permission
    // set, so no need to create another one here.
    $this->protocol->addMember($this->manager, ['member']);

    $node = Node::create([
      'type' => 'thing',
      'title' => $this->randomString(),
      'uid' => $this->owner->id(),
      'moderation_state' => 'published',
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();

    $this->assertFalse($node->access('update', $this->manager), 'Sanity check: plain member still lacks update access.');

    $action = $this->createAction('archived');
    $this->assertTrue($action->access($node, $this->manager));
  }

  /**
   * The same plain OG member is still denied a non-archive/restore
   * transition, which stays gated on 'update' -- the view-based carve-out
   * is narrow, not a general loosening.
   */
  public function testAccessDeniedForPlainMemberOnNonArchiveTransition(): void {
    $role = Role::load('mukurtu_manager');
    $role->grantPermission('use mukurtu_default_content_workflow transition create_new_draft');
    $role->save();

    // Og::addGroup() already auto-creates a default 'member' role (empty
    // permissions) for every group bundle -- matching the real shipped
    // og.og_role.protocol-protocol-protocol_member.yml's empty permission
    // set, so no need to create another one here.
    $this->protocol->addMember($this->manager, ['member']);

    $node = Node::create([
      'type' => 'thing',
      'title' => $this->randomString(),
      'uid' => $this->owner->id(),
      'moderation_state' => 'published',
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();

    $action = $this->createAction('draft');
    $this->assertFalse($action->access($node, $this->manager));
  }

  /**
   * End-to-end reproduction of the PR #2036 QA bug: a Contributor holding
   * only real per-protocol "update own" permission (no archive/restore
   * transition rights) must not be able to bulk-publish their own content
   * straight out of 'archived', even though the site-wide
   * 'use mukurtu_default_content_workflow transition publish' permission
   * (granted to every authenticated user) makes 'publish' a technically
   * valid transition per getValidTransitions() alone.
   */
  public function testAccessDeniedForContributorPublishingOwnArchivedContent(): void {
    $contributorRole = OgRole::create([
      'name' => 'contributor_editor',
      'label' => 'Contributor',
      'permissions' => ['apply protocol', 'create thing node', 'edit own thing content'],
    ]);
    $contributorRole->setGroupType('protocol');
    $contributorRole->setGroupBundle('protocol');
    $contributorRole->save();

    $contributor = User::create(['name' => $this->randomMachineName()]);
    $contributor->save();
    $this->protocol->addMember($contributor, ['contributor_editor']);

    $node = Node::create([
      'type' => 'thing',
      'title' => $this->randomString(),
      'uid' => $contributor->id(),
      'moderation_state' => 'archived',
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();

    $transitions = \Drupal::service('content_moderation.state_transition_validation')
      ->getValidTransitions($node, $contributor);
    $this->assertArrayHasKey('publish', $transitions, 'Sanity check: transition-permission alone says yes.');

    $action = $this->createAction('published');
    $this->assertFalse($action->access($node, $contributor));
  }

  /**
   * Access is denied outright for a non-moderated bundle.
   */
  public function testAccessDeniedForNonModeratedNode(): void {
    $node = Node::create([
      'type' => 'landing_page',
      'title' => $this->randomString(),
      'uid' => $this->owner->id(),
    ]);
    $node->save();

    $action = $this->createAction('published');
    $this->assertFalse($action->access($node, $this->manager));
  }

  /**
   * execute() correctly transitions state and creates a new revision.
   */
  public function testExecuteTransitionsStateAndCreatesRevision(): void {
    $node = $this->createModeratedNode('article', 'draft');
    $original_revision_id = $node->getRevisionId();

    $this->container->get('current_user')->setAccount($this->manager);
    $action = $this->createAction('published');
    $action->execute($node);

    $node = Node::load($node->id());
    $this->assertEquals('published', $node->moderation_state->value);
    $this->assertNotEquals($original_revision_id, $node->getRevisionId());
  }

  /**
   * execute() skips (without saving) when the target state is no longer
   * reachable from the node's current state.
   */
  public function testExecuteSkipsStaleTransition(): void {
    // Published -> published is not itself a real change; use a target
    // that's definitely unreachable from 'published' in this workflow: there
    // is no direct published -> draft-only-from-archived-style transition
    // here, so configure the action for a target state not present at all.
    $node = $this->createModeratedNode('article', 'published');
    $original_revision_id = $node->getRevisionId();

    $this->container->get('current_user')->setAccount($this->manager);
    $action = $this->createAction('nonexistent_state');
    $action->execute($node);

    $node = Node::load($node->id());
    $this->assertEquals('published', $node->moderation_state->value);
    $this->assertEquals($original_revision_id, $node->getRevisionId());
  }

}
