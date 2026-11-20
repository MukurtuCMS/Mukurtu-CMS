<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_api\Kernel\JsonApi;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeAccessRebuild;
use Drupal\og\Og;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Audits what core JSON:API exposes across cultural protocols (#400).
 *
 * Research spike. Each test asserts the secure behavior: someone outside a
 * strict protocol learns nothing about content under it. Every restricted
 * fixture has "SECRET" in its label or text, so a probe checks the raw
 * response for that marker.
 *
 * A confirmed gap is recorded with markTestIncomplete('Finding N: ...') so
 * the suite still passes while each gap stays visible and numbered. The
 * numbers match the "Spike results" section of the #400 options report.
 */
#[Group('mukurtu_api')]
class JsonApiProtocolAccessTest extends KernelTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'entity_reference_revisions',
    'field',
    'file',
    'filter',
    'geofield',
    'image',
    'jsonapi',
    'leaflet',
    'media',
    'mukurtu_core',
    'mukurtu_protocol',
    'node',
    'og',
    'options',
    'paragraphs',
    'serialization',
    'system',
    'taxonomy',
    'text',
    'user',
    'workflows',
  ];

  /**
   * Someone signed in but in no protocol.
   */
  protected AccountInterface $outsider;

  /**
   * A member of strict protocol A.
   */
  protected AccountInterface $member;

  /**
   * Content, keyed by a short name such as 'open_node' or 'secret_media'.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected array $fixtures = [];

  /**
   * {@inheritdoc}
   */
  protected function setUpFilesystem(): void {
    parent::setUpFilesystem();
    // Register the private stream wrapper before the container is built.
    $this->setSetting('file_private_path', $this->siteDirectory . '/private');
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['field', 'system', 'image', 'file', 'og', 'media', 'taxonomy', 'node', 'filter']);
    $entityTypes = [
      'community', 'file', 'media', 'node', 'og_membership',
      'paragraph', 'protocol', 'taxonomy_term', 'user', 'workflow',
    ];
    foreach ($entityTypes as $entityType) {
      $this->installEntitySchema($entityType);
    }
    $this->installSchema('file', 'file_usage');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('mukurtu_protocol', ['mukurtu_protocol_map', 'mukurtu_protocol_access']);

    // Burn uid 1, which bypasses every access check, and run fixture setup
    // as it so CulturalProtocolItem::preSave() keeps the protocols we set.
    $admin = User::create(['name' => 'uid1-placeholder', 'status' => 1]);
    $admin->save();
    $this->container->get('current_user')->setAccount($admin);

    // The view permissions mukurtu_install() grants on a new site.
    $anonymous = Role::create(['id' => RoleInterface::ANONYMOUS_ID, 'label' => 'Anonymous']);
    $authenticated = Role::create(['id' => RoleInterface::AUTHENTICATED_ID, 'label' => 'Authenticated']);
    foreach (['access content', 'view media', 'view published community entities'] as $permission) {
      $anonymous->grantPermission($permission);
      $authenticated->grantPermission($permission);
    }
    $authenticated->grantPermission('view published protocol control entities');
    $anonymous->save();
    $authenticated->save();

    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');
    \Drupal::service(NodeAccessRebuild::class)->rebuild();

    $this->createContentModel();
    $this->createFixtures();
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Creates a node type, vocabulary, paragraph type and media type.
   */
  protected function createContentModel(): void {
    NodeType::create(['type' => 'thing', 'name' => 'Thing'])->save();
    Vocabulary::create(['vid' => 'keywords', 'name' => 'Keywords'])->save();
    ParagraphsType::create(['id' => 'text_block', 'label' => 'Text block'])->save();
    $this->createMediaType('file', ['id' => 'document']);
    // Media files live in private storage on real sites.
    FieldStorageConfig::load('media.field_media_file')
      ->setSetting('uri_scheme', 'private')
      ->save();

    $fields = [
      ['paragraph', 'text_block', 'field_text', 'string', []],
      ['node', 'thing', 'field_keywords', 'entity_reference', ['target_type' => 'taxonomy_term']],
      ['node', 'thing', 'field_media', 'entity_reference', ['target_type' => 'media']],
      ['node', 'thing', 'field_blocks', 'entity_reference_revisions', ['target_type' => 'paragraph']],
    ];
    foreach ($fields as [$entityType, $bundle, $name, $type, $settings]) {
      FieldStorageConfig::create([
        'entity_type' => $entityType,
        'field_name' => $name,
        'type' => $type,
        'settings' => $settings,
        'cardinality' => -1,
      ])->save();
      FieldConfig::create([
        'entity_type' => $entityType,
        'bundle' => $bundle,
        'field_name' => $name,
      ])->save();
    }
  }

  /**
   * Creates one open and one strict copy of each kind of content.
   */
  protected function createFixtures(): void {
    $community = Community::create(['name' => 'Open community', 'field_access_mode' => 'public']);
    $community->save();
    $open = Protocol::create([
      'name' => 'Open protocol',
      'field_communities' => [$community->id()],
      'field_access_mode' => 'open',
    ]);
    $open->save();
    $strict = Protocol::create([
      'name' => 'SECRET protocol A',
      'field_communities' => [$community->id()],
      'field_access_mode' => 'strict',
    ]);
    $strict->save();
    $secretCommunity = Community::create(['name' => 'SECRET community', 'field_access_mode' => 'community-only']);
    $secretCommunity->save();
    $this->fixtures += [
      'open_protocol' => $open,
      'secret_protocol' => $strict,
      'secret_community' => $secretCommunity,
    ];

    $this->outsider = User::create(['name' => 'outsider', 'status' => 1]);
    $this->outsider->save();
    $this->member = User::create(['name' => 'member-a', 'status' => 1]);
    $this->member->save();
    $strict->addMember($this->member);
    $secretCommunity->addMember($this->member);

    $this->container->get('file_system')->mkdir('private://', NULL, TRUE);
    foreach (['open' => $open, 'secret' => $strict] as $key => $protocol) {
      $label = $key === 'secret' ? 'SECRET' : 'Public';

      $file = File::create([
        'uri' => "private://$key-file.txt",
        'filename' => "$label-file.txt",
        'status' => 1,
      ]);
      file_put_contents($file->getFileUri(), 'test');
      $file->save();

      $media = Media::create([
        'bundle' => 'document',
        'name' => "$label media",
        'field_media_file' => $file,
        'status' => 1,
      ]);
      $media->setSharingSetting('any');
      $media->setProtocols([$protocol]);
      $media->save();

      $term = Term::create(['vid' => 'keywords', 'name' => "$label term"]);
      $term->save();

      $paragraph = Paragraph::create(['type' => 'text_block', 'field_text' => "$label paragraph"]);

      $node = Node::create([
        'type' => 'thing',
        'title' => "$label node",
        'status' => 1,
        'uid' => $this->container->get('current_user')->id(),
        'field_keywords' => [$term],
        'field_media' => [$media],
        'field_blocks' => [$paragraph],
      ]);
      $node->setSharingSetting('any');
      $node->setProtocols([$protocol]);
      $node->save();

      $this->fixtures += [
        "{$key}_file" => $file,
        "{$key}_media" => $media,
        "{$key}_term" => $term,
        "{$key}_paragraph" => $node->get('field_blocks')->entity,
        "{$key}_node" => $node,
      ];
    }

    // An open item that points at restricted media, as when a public item
    // has one sensitive attachment.
    $mixed = Node::create([
      'type' => 'thing',
      'title' => 'Public node with restricted media',
      'status' => 1,
      'field_media' => [$this->fixtures['secret_media']],
    ]);
    $mixed->setSharingSetting('any');
    $mixed->setProtocols([$open]);
    $mixed->save();
    $this->fixtures['mixed_node'] = $mixed;
  }

  /**
   * Sends a JSON:API GET request as the given account.
   *
   * @return array{0: int, 1: string}
   *   The status code and the raw response body.
   */
  protected function get(string $path, AccountInterface $account): array {
    $this->container->get('current_user')->setAccount($account);
    $request = Request::create($path);
    $request->headers->set('Accept', 'application/vnd.api+json');
    $response = $this->container->get('http_kernel')->handle($request);
    return [$response->getStatusCode(), (string) $response->getContent()];
  }

  /**
   * People who should learn nothing about strict protocol A.
   *
   * @return \Drupal\Core\Session\AccountInterface[]
   *   Keyed by a short description.
   */
  protected function nonMembers(): array {
    return ['anonymous' => new AnonymousUserSession(), 'outsider' => $this->outsider];
  }

  /**
   * Returns the non-members whose response to $path contains $marker.
   *
   * Also checks that the probe is real: every request succeeds, and a member
   * of protocol A does get the marker, so a broken route can't pass.
   *
   * @return string[]
   *   Descriptions of the accounts that saw restricted data.
   */
  protected function leaksTo(string $path, string $marker = 'SECRET', bool $memberSeesIt = TRUE): array {
    [$status, $body] = $this->get($path, $this->member);
    $this->assertSame(200, $status, "member: $path\n$body");
    if ($memberSeesIt) {
      $this->assertStringContainsString($marker, $body, "A member of A sees $marker at $path.");
    }
    $leaks = [];
    foreach ($this->nonMembers() as $who => $account) {
      [$status, $body] = $this->get($path, $account);
      $this->assertSame(200, $status, "$who: $path\n$body");
      if (str_contains($body, $marker)) {
        $leaks[] = $who;
      }
    }
    return $leaks;
  }

  /**
   * Builds the individual resource path for a fixture.
   */
  protected function resourcePath(string $key): string {
    $entity = $this->fixtures[$key];
    return sprintf('/jsonapi/%s/%s/%s', $entity->getEntityTypeId(), $entity->bundle(), $entity->uuid());
  }

  /**
   * The fixtures work: a member of A sees A's node, others are refused.
   */
  public function testFixturesBehaveLikeTheSite(): void {
    [$status, $body] = $this->get($this->resourcePath('secret_node'), $this->member);
    $this->assertSame(200, $status, $body);
    $this->assertStringContainsString('SECRET node', $body);

    foreach ($this->nonMembers() as $who => $account) {
      $this->assertFalse($this->fixtures['secret_node']->access('view', $account), "$who can't view the node on the site.");
      $this->assertTrue($this->fixtures['open_node']->access('view', $account), "$who can view the open node on the site.");
    }
  }

  /**
   * Individual restricted items return 403 with nothing in the body.
   */
  public function testIndividualResources(): void {
    foreach (['secret_node', 'secret_media', 'secret_paragraph', 'secret_file', 'secret_protocol', 'secret_community'] as $key) {
      foreach ($this->nonMembers() as $who => $account) {
        [$status, $body] = $this->get($this->resourcePath($key), $account);
        $this->assertSame(403, $status, "$who: $key");
        $this->assertStringNotContainsString('SECRET', $body, "$who: $key");
      }
    }
  }

  /**
   * Node and media lists leave out restricted items.
   */
  public function testNodeAndMediaCollections(): void {
    $this->assertSame([], $this->leaksTo('/jsonapi/node/thing'));
    $this->assertSame([], $this->leaksTo('/jsonapi/media/document'));
  }

  /**
   * Protocol and community lists leave out strict protocols.
   */
  public function testProtocolCollections(): void {
    $this->assertSame([], $this->leaksTo('/jsonapi/protocol/protocol'));
    $this->assertSame([], $this->leaksTo('/jsonapi/community/community'));
  }

  /**
   * Paragraph lists leave out paragraphs on restricted items.
   */
  public function testParagraphCollection(): void {
    $this->assertSame([], $this->leaksTo('/jsonapi/paragraph/text_block'));
  }

  /**
   * File lists leave out files attached to restricted media.
   */
  public function testFileCollection(): void {
    $this->assertSame([], $this->leaksTo('/jsonapi/file/file'));
  }

  /**
   * Term names used only on restricted items stay hidden (#2643).
   *
   * On the site, TermVisibility hides these names from term pages and
   * browse facets.
   */
  public function testTermCollection(): void {
    $leaks = $this->leaksTo('/jsonapi/taxonomy_term/keywords');
    if ($leaks === ['anonymous', 'outsider']) {
      $this->markTestIncomplete('Finding 1: /jsonapi/taxonomy_term lists term names used only on restricted items to anonymous visitors and non-members. TermVisibility (#2643) is applied in a controller and a Views filter, not in term access, so JSON:API bypasses it.');
    }
    $this->assertSame([], $leaks);
  }

  /**
   * Protocol memberships aren't listed to people outside the protocol.
   */
  public function testMembershipCollection(): void {
    // A membership names its group by UUID, not by label.
    // Members can't list memberships either, so there's no positive control.
    $this->assertSame([], $this->leaksTo('/jsonapi/og_membership/default', $this->fixtures['secret_protocol']->uuid(), FALSE));
  }

  /**
   * Including a restricted relationship doesn't reveal it.
   */
  public function testIncludeRestrictedMedia(): void {
    $this->assertSame([], $this->leaksTo($this->resourcePath('mixed_node') . '?include=field_media,field_media.field_media_file'));
  }

  /**
   * An open item's relationship data doesn't identify restricted media.
   *
   * JSON:API lists a relationship's type and UUID even when the target
   * can't be viewed. A UUID reveals that a hidden item exists and lets
   * someone track it across items, but not what it is.
   */
  public function testRelationshipIdentifiers(): void {
    $leaks = $this->leaksTo($this->resourcePath('mixed_node'), $this->fixtures['secret_media']->uuid());
    if ($leaks === ['anonymous', 'outsider']) {
      $this->markTestIncomplete('Finding 2: an open item\'s relationships list the type and UUID of restricted media it references. This reveals that a hidden item exists, but not its name or file.');
    }
    $this->assertSame([], $leaks);
  }

  /**
   * The user list doesn't name people to anonymous visitors.
   */
  public function testUserCollection(): void {
    $leaks = $this->leaksTo('/jsonapi/user/user', 'member-a');
    if ($leaks === ['anonymous', 'outsider']) {
      $this->markTestIncomplete('Finding 3: /jsonapi/user/user lists the display name and UUID of every active account, including administrators, to anonymous visitors. Core allows "view label" on users, and the site has no page that lists accounts like this.');
    }
    $this->assertSame([], $leaks);
  }

  /**
   * Filtering on a restricted item's field doesn't confirm it exists.
   *
   * If filtering open items by the name of restricted media they reference
   * changes the result, someone can guess names one at a time.
   */
  public function testFilterOnRestrictedRelationship(): void {
    $hit = '/jsonapi/node/thing?filter[field_media.name]=' . rawurlencode('SECRET media');
    $miss = '/jsonapi/node/thing?filter[field_media.name]=' . rawurlencode('No such media');
    // Control: the filter works for a member, who gets both items that use it.
    [$status, $body] = $this->get($hit, $this->member);
    $this->assertSame(200, $status, $body);
    $this->assertCount(2, json_decode($body, TRUE)['data']);
    foreach ($this->nonMembers() as $who => $account) {
      [$hitStatus, $hitBody] = $this->get($hit, $account);
      [$missStatus, $missBody] = $this->get($miss, $account);
      $hitCount = count(json_decode($hitBody, TRUE)['data'] ?? []);
      $missCount = count(json_decode($missBody, TRUE)['data'] ?? []);
      $this->assertSame([$missStatus, $missCount], [$hitStatus, $hitCount], "$who can tell restricted media's name from a filter.");
    }
  }

  /**
   * Nobody can filter paragraphs, so their text can't be guessed.
   *
   * JSON:API only filters an entity type that has a
   * hook_jsonapi_ENTITY_TYPE_filter_access() implementation. Core has none
   * for paragraphs, so TemporaryQueryGuard makes every paragraph filter match
   * nothing, even for members. The same applies to communities, protocols and
   * multipage items. A real Mukurtu API that needs these filters must add
   * those hooks and make them protocol-aware.
   */
  public function testParagraphFilterIsClosed(): void {
    $path = '/jsonapi/paragraph/text_block?filter[field_text]=' . rawurlencode('SECRET paragraph');
    foreach (['member' => $this->member] + $this->nonMembers() as $who => $account) {
      [$status, $body] = $this->get($path, $account);
      $this->assertSame(200, $status, "$who: $body");
      $this->assertSame([], json_decode($body, TRUE)['data'], "$who: $body");
      $this->assertSame(0, $this->omittedCount($body), "$who: $body");
    }
  }

  /**
   * Counts the items JSON:API reports as omitted for lack of access.
   */
  protected function omittedCount(string $body): int {
    $links = json_decode($body, TRUE)['meta']['omitted']['links'] ?? [];
    unset($links['help']);
    return count($links);
  }

}
