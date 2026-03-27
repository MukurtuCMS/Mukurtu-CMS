<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_digital_heritage\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_digital_heritage\Entity\DigitalHeritage;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Base class for Digital Heritage kernel tests.
 */
abstract class DigitalHeritageTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'comment',
    'content_moderation',
    'entity_reference_revisions',
    'field',
    'file',
    'filter',
    'geofield',
    'image',
    'link',
    'media',
    'node',
    'node_access_test',
    'og',
    'options',
    'original_date',
    'paragraphs',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'workflows',
    'mukurtu_core',
    'mukurtu_digital_heritage',
    'mukurtu_drafts',
    'mukurtu_local_contexts',
    'mukurtu_protocol',
  ];

  /**
   * The current test user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected User $currentUser;

  /**
   * A community for the content.
   *
   * @var \Drupal\mukurtu_protocol\Entity\Community
   */
  protected Community $community;

  /**
   * A protocol for the content.
   *
   * @var \Drupal\mukurtu_protocol\Entity\Protocol
   */
  protected Protocol $protocol;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('system', 'sequences');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_map');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_access');

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('taxonomy_vocabulary');
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('workflow');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');
    $this->installEntitySchema('media');
    $this->installEntitySchema('file');

    $this->installConfig(['filter', 'og', 'system']);

    // Create the digital_heritage node type so hook_entity_bundle_info_alter
    // in mukurtu_digital_heritage assigns the DigitalHeritage bundle class.
    // Without this, Node::create(['type' => 'digital_heritage']) returns a
    // base Node instance and setSharingSetting() / setProtocols() are missing.
    NodeType::create([
      'type' => 'digital_heritage',
      'name' => 'Digital Heritage',
    ])->save();

    node_access_rebuild();

    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');

    // Create the category vocabulary — required for digital heritage items.
    Vocabulary::create(['vid' => 'category', 'name' => 'Category'])->save();

    // Authenticated role with content permissions.
    $role = Role::create([
      'id' => 'authenticated',
      'label' => 'authenticated',
    ]);
    $role->grantPermission('access content');
    $role->grantPermission('create digital_heritage content');
    $role->save();

    // Protocol steward OG role.
    $protocolStewardRole = OgRole::create([
      'name' => 'protocol_steward',
      'label' => 'Protocol Steward',
      'permissions' => [
        'add user',
        'apply protocol',
        'administer permissions',
        'approve and deny subscription',
        'create digital_heritage node',
        'delete any digital_heritage node',
        'delete own digital_heritage node',
        'update any digital_heritage node',
        'update own digital_heritage node',
        'manage members',
        'update group',
      ],
    ]);
    $protocolStewardRole->setGroupType('protocol');
    $protocolStewardRole->setGroupBundle('protocol');
    $protocolStewardRole->save();

    // Contributor OG role.
    $contributorRole = OgRole::create([
      'name' => 'contributor',
      'label' => 'Contributor',
      'permissions' => [
        'create digital_heritage node',
        'delete own digital_heritage node',
        'update own digital_heritage node',
      ],
    ]);
    $contributorRole->setGroupType('protocol');
    $contributorRole->setGroupBundle('protocol');
    $contributorRole->save();

    $this->container = \Drupal::getContainer();

    $user = User::create(['name' => $this->randomString()]);
    $user->save();
    $this->currentUser = $user;
    $this->container->get('current_user')->setAccount($user);

    $community = Community::create(['name' => 'Test Community']);
    $community->save();
    $community->addMember($user);
    $this->community = $community;

    $protocol = Protocol::create([
      'name' => 'Test Protocol',
      'field_communities' => [$community->id()],
      'field_access_mode' => 'open',
    ]);
    $protocol->save();
    $protocol->addMember($user, ['protocol_steward']);
    $this->protocol = $protocol;
  }

  /**
   * Create a pre-existing category taxonomy term.
   *
   * field_category has auto_create=FALSE so terms must exist before
   * being referenced on a digital heritage item.
   */
  protected function createCategory(string $name): Term {
    $term = Term::create(['name' => $name, 'vid' => 'category']);
    $term->save();
    return $term;
  }

  /**
   * Create and return an unsaved DigitalHeritage node.
   *
   * @param string $title
   *   The node title.
   * @param \Drupal\taxonomy\Entity\Term[] $categories
   *   One or more pre-existing category terms.
   *
   * @return \Drupal\mukurtu_digital_heritage\Entity\DigitalHeritage
   */
  protected function buildDigitalHeritage(string $title, array $categories = []): DigitalHeritage {
    /** @var \Drupal\mukurtu_digital_heritage\Entity\DigitalHeritage $item */
    $item = Node::create([
      'title' => $title,
      'type' => 'digital_heritage',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
      'field_category' => $categories,
    ]);
    $item->setSharingSetting('any');
    $item->setProtocols([$this->protocol]);
    return $item;
  }

}
