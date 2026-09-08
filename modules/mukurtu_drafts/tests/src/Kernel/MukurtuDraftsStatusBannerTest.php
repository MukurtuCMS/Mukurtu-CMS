<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_drafts\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\workflows\Entity\Workflow;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_drafts_preprocess_node()'s status banner label/modifier.
 *
 * @see mukurtu_drafts_preprocess_node()
 */
#[Group('mukurtu_drafts')]
class MukurtuDraftsStatusBannerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'field',
    'file',
    'image',
    'media',
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

    // See MukurtuDraftsEntityTest - mukurtu_drafts itself is not enabled
    // here, since its declared dependency on mukurtu_core pulls in a large
    // contrib module chain not needed to exercise this one hook.
    require_once \Drupal::service('extension.list.module')->getPath('mukurtu_drafts') . '/mukurtu_drafts.module';

    $this->installSchema('node', ['node_access']);
    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('content_moderation_state');

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    // ContentModerationTestTrait::createEditorialWorkflow() only defines
    // draft/published/archived - mukurtu_drafts' own banner_states map
    // additionally covers needs_review/revision_requested (Mukurtu's real
    // editorial workflow, mukurtu_editorial_workflow, has all 5), so build
    // a workflow with all of them here instead.
    $workflow = Workflow::create([
      'type' => 'content_moderation',
      'id' => 'mukurtu_test_workflow',
      'label' => 'Mukurtu Test Workflow',
      'type_settings' => [
        'states' => [
          'draft' => ['label' => 'Draft', 'weight' => 0, 'published' => FALSE, 'default_revision' => FALSE],
          'needs_review' => ['label' => 'Needs Review', 'weight' => 1, 'published' => FALSE, 'default_revision' => FALSE],
          'revision_requested' => ['label' => 'Revision Requested', 'weight' => 2, 'published' => FALSE, 'default_revision' => FALSE],
          'published' => ['label' => 'Published', 'weight' => 3, 'published' => TRUE, 'default_revision' => TRUE],
          'archived' => ['label' => 'Archived', 'weight' => 4, 'published' => FALSE, 'default_revision' => TRUE],
        ],
        'transitions' => [
          'submit' => ['label' => 'Submit', 'from' => ['draft'], 'to' => 'needs_review', 'weight' => 0],
          'publish' => ['label' => 'Publish', 'from' => ['needs_review', 'revision_requested'], 'to' => 'published', 'weight' => 1],
          'request_revision' => ['label' => 'Request revisions', 'from' => ['needs_review'], 'to' => 'revision_requested', 'weight' => 2],
          'archive' => ['label' => 'Archive', 'from' => ['published'], 'to' => 'archived', 'weight' => 3],
        ],
      ],
    ]);
    $workflow->save();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'page');
    $workflow->save();
  }

  /**
   * Builds a node in the given state and returns the banner render array
   * mukurtu_drafts_preprocess_node() would inject for it, or NULL.
   */
  protected function getBannerFor(string $moderation_state): ?array {
    $node = Node::create([
      'type' => 'page',
      'title' => $this->randomString(),
      'moderation_state' => $moderation_state,
    ]);
    $node->save();

    $variables = ['node' => $node];
    mukurtu_drafts_preprocess_node($variables);
    return $variables['title_prefix']['status_banner'] ?? NULL;
  }

  /**
   * Without mukurtu_submissions installed, the generic state labels used
   * before this feature existed must be completely unaffected - the new
   * moduleExists() guard has to fail closed, not assume the module (and
   * mukurtu_submissions_get_submission_for_entity()) is available.
   */
  public function testGenericLabelsUnaffectedWithoutSubmissionsModule(): void {
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('mukurtu_submissions'));

    $expected = [
      'draft' => 'Draft',
      'needs_review' => 'Awaiting Review',
      'revision_requested' => 'Edits Needed',
      'archived' => 'Archived',
    ];
    foreach ($expected as $state => $label) {
      $banner = $this->getBannerFor($state);
      $this->assertNotNull($banner, "A banner should render for the '$state' state.");
      $this->assertEquals($label, $banner['#value']);
      $this->assertContains('node--status-banner--' . str_replace('_', '-', $state), $banner['#attributes']['class']);
    }
  }

  /**
   * Published content never gets a banner, submission-sourced or not.
   */
  public function testPublishedContentHasNoBanner(): void {
    $this->assertNull($this->getBannerFor('published'));
  }

}
