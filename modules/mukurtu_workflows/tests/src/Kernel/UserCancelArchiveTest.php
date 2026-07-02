<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_workflows\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;

/**
 * Tests that hook_user_cancel() only archives currently-published content.
 *
 * @group mukurtu_workflows
 */
class UserCancelArchiveTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('node', ['node_access']);
    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('content_moderation_state');

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    $workflow = $this->createEditorialWorkflow();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', 'page');
  }

  /**
   * Creates a saved user to own test content.
   */
  protected function createOwner(): User {
    $owner = User::create(['name' => $this->randomMachineName()]);
    $owner->save();
    return $owner;
  }

  /**
   * Creates a moderated node in the given state, owned by $uid.
   */
  protected function createModeratedNode(string $moderation_state, int $uid): Node {
    $node = Node::create([
      'type' => 'page',
      'title' => $this->randomString(),
      'uid' => $uid,
      'moderation_state' => $moderation_state,
    ]);
    $node->save();
    return $node;
  }

  /**
   * Published content is moved to the archived state on cancellation.
   */
  public function testPublishedContentIsArchivedOnCancel(): void {
    $owner = $this->createOwner();
    $node = $this->createModeratedNode('published', (int) $owner->id());

    mukurtu_workflows_user_cancel([], $owner, 'user_cancel_block_unpublish');

    $node = Node::load($node->id());
    $this->assertEquals('archived', $node->moderation_state->value);
    $this->assertFalse($node->isPublished());
  }

  /**
   * Draft content was never public, so it must be left untouched.
   */
  public function testDraftContentIsUntouchedOnCancel(): void {
    $owner = $this->createOwner();
    $node = $this->createModeratedNode('draft', (int) $owner->id());

    mukurtu_workflows_user_cancel([], $owner, 'user_cancel_block_unpublish');

    $node = Node::load($node->id());
    $this->assertEquals('draft', $node->moderation_state->value);
  }

  /**
   * Cancellation methods other than "block and unpublish" are no-ops here.
   */
  public function testOtherCancelMethodsAreIgnored(): void {
    $owner = $this->createOwner();
    $node = $this->createModeratedNode('published', (int) $owner->id());

    mukurtu_workflows_user_cancel([], $owner, 'user_cancel_block');

    $node = Node::load($node->id());
    $this->assertEquals('published', $node->moderation_state->value);
  }

}
