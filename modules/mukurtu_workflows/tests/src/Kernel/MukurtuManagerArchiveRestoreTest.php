<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_workflows\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Service\ModerationTransitionAccessResolver;
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
 * Tests that granting Mukurtu Manager archive/restore on the Default
 * workflow closes the gap on structural content (article) without giving
 * reach into protocol-gated content (thing), which stays independently
 * gated by OG protocol membership.
 */
#[Group('mukurtu_workflows')]
class MukurtuManagerArchiveRestoreTest extends KernelTestBase {

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
    // protocol-gated.
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'thing', 'name' => 'Protocol Controlled Thing'])->save();

    // Trim the shipped bundle list down to the two bundles this test
    // actually creates -- the others (collection, person, etc.) don't
    // exist here, and calculateDependencies() requires every listed
    // bundle to have a real node type.
    $workflow = Workflow::load('mukurtu_default_content_workflow');
    $type_settings = $workflow->get('type_settings');
    $type_settings['entity_types']['node'] = ['article'];
    $workflow->set('type_settings', $type_settings);
    $workflow->save();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', 'thing');

    // Baseline permission every authenticated user has on a real site.
    $authenticated = Role::create(['id' => 'authenticated', 'label' => 'Authenticated user']);
    $authenticated->grantPermission('access content');
    $authenticated->save();

    $role = Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager']);
    $role->grantPermission('edit any article content');
    $role->grantPermission('view any unpublished content');
    $role->grantPermission('use mukurtu_default_content_workflow transition archive');
    $role->grantPermission('use mukurtu_default_content_workflow transition restore');
    $role->grantPermission('use mukurtu_editorial_workflow transition archive');
    $role->grantPermission('use mukurtu_editorial_workflow transition restore');
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
   * Manager can archive/restore article via baseline edit-any + transition
   * permissions -- structural content isn't OG protocol-gated.
   */
  public function testManagerCanArchiveAndRestoreArticle(): void {
    $published = $this->createModeratedNode('article', 'published');
    $this->assertTrue($published->access('update', $this->manager));
    $transitions = \Drupal::service('content_moderation.state_transition_validation')
      ->getValidTransitions($published, $this->manager);
    $this->assertArrayHasKey('archive', $transitions);

    $archived = $this->createModeratedNode('article', 'archived');
    $this->assertTrue($archived->access('update', $this->manager));
    $transitions = \Drupal::service('content_moderation.state_transition_validation')
      ->getValidTransitions($archived, $this->manager);
    $this->assertArrayHasKey('restore', $transitions);
  }

  /**
   * Manager cannot update a protocol-gated node they aren't a protocol
   * member of, despite holding the site-wide transition permission --
   * MukurtuProtocolNodeAccessControlHandler's OG-scoped check is the real
   * boundary, independent of the workflow transition permission.
   */
  public function testManagerCannotUpdateProtocolGatedContent(): void {
    $node = Node::create([
      'type' => 'thing',
      'title' => $this->randomString(),
      'uid' => $this->owner->id(),
      'moderation_state' => 'published',
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();

    $this->assertFalse($node->access('update', $this->manager));
  }

  /**
   * Same as testManagerCannotUpdateProtocolGatedContent, but for the
   * Editorial workflow -- confirms the Editorial archive/restore grant is
   * equally safe: it does not give Mukurtu Manager reach into
   * protocol-gated content, since that's blocked at the OG-scoped node
   * access layer regardless of which workflow governs the transition.
   */
  public function testManagerCannotUpdateProtocolGatedContentOnEditorialWorkflow(): void {
    $default = Workflow::load('mukurtu_default_content_workflow');
    $default_settings = $default->get('type_settings');
    $default_settings['entity_types']['node'] = array_values(array_diff($default_settings['entity_types']['node'], ['thing']));
    $default->set('type_settings', $default_settings)->save();

    $editorial = Workflow::load('mukurtu_editorial_workflow');
    $this->addEntityTypeAndBundleToWorkflow($editorial, 'node', 'thing');

    $node = Node::create([
      'type' => 'thing',
      'title' => $this->randomString(),
      'uid' => $this->owner->id(),
      'moderation_state' => 'draft',
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();

    $this->assertFalse($node->access('update', $this->manager));
  }

  /**
   * A manager who is only a plain protocol MEMBER (empty OG permissions --
   * matching the real og.og_role.protocol-protocol-protocol_member.yml)
   * still can't update protocol-gated content, but CAN now archive/restore
   * it: those two transitions are gated on 'view' access (granted via
   * node grants to any member), not 'update', since they're pure
   * moderation decisions rather than content edits. Every other
   * transition still requires 'update' and stays blocked.
   */
  public function testManagerCanArchiveProtocolGatedContentAsPlainMember(): void {
    // Og::addGroup() already auto-creates a default 'member' role (empty
    // permissions) for every group bundle -- matching the real shipped
    // og.og_role.protocol-protocol-protocol_member.yml's empty permission
    // set, so no need to create another one here.
    $this->protocol->addMember($this->manager, ['member']);

    $published = Node::create([
      'type' => 'thing',
      'title' => $this->randomString(),
      'uid' => $this->owner->id(),
      'moderation_state' => 'published',
    ]);
    $published->setSharingSetting('any');
    $published->setProtocols([$this->protocol]);
    $published->save();

    $this->assertFalse($published->access('update', $this->manager), 'Plain member still cannot update protocol-gated content.');
    $this->assertTrue($published->access('view', $this->manager), 'Plain member can view content in their own protocol.');

    $resolver = new ModerationTransitionAccessResolver($this->container->get('content_moderation.state_transition_validation'));
    $accessible = $resolver->getAccessibleTransitions($published, $this->manager);
    $this->assertArrayHasKey('archive', $accessible, 'Archive is accessible via view access + transition permission.');

    $archived = Node::create([
      'type' => 'thing',
      'title' => $this->randomString(),
      'uid' => $this->owner->id(),
      'moderation_state' => 'archived',
    ]);
    $archived->setSharingSetting('any');
    $archived->setProtocols([$this->protocol]);
    $archived->save();

    $accessible = $resolver->getAccessibleTransitions($archived, $this->manager);
    $this->assertArrayHasKey('restore', $accessible, 'Restore is accessible via view access + transition permission.');
  }

}
