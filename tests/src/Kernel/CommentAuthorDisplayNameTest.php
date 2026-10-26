<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Kernel;

use Drupal\comment\CommentInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that comments credit the author by their display name (#2165).
 *
 * Mukurtu keeps the display name in field_display_name, and nothing maps
 * getDisplayName() to it, so comments used to show the account name.
 *
 * @see mukurtu_v4_preprocess_comment()
 */
#[Group('mukurtu')]
class CommentAuthorDisplayNameTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'comment'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'user']);

    FieldStorageConfig::create([
      'field_name' => 'field_display_name',
      'entity_type' => 'user',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_display_name',
      'entity_type' => 'user',
      'bundle' => 'user',
      'label' => 'Display name',
    ])->save();

    require_once \Drupal::root() . '/' . \Drupal::service('extension.list.theme')->getPath('mukurtu_v4') . '/mukurtu_v4.theme';
  }

  /**
   * Runs the comment preprocess for a comment owned by the given user.
   */
  protected function preprocess(UserInterface $owner): array {
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('getOwner')->willReturn($owner);
    $comment->method('getCreatedTime')->willReturn(1780000000);
    $variables = ['comment' => $comment];
    mukurtu_v4_preprocess_comment($variables);
    return $variables;
  }

  /**
   * Renders the submitted line the way the template prints it.
   */
  protected function renderSubmitted(array $variables): string {
    $submitted = is_array($variables['submitted']) ? $variables['submitted'] : ['#markup' => $variables['submitted']];
    return (string) \Drupal::service('renderer')->executeInRenderContext(
      new RenderContext(),
      fn () => \Drupal::service('renderer')->render($submitted),
    );
  }

  /**
   * A display name is used in the submitted line and the comment's label.
   */
  public function testUsesDisplayName(): void {
    $owner = User::create(['name' => 'jdoe', 'field_display_name' => 'Jane Doe']);
    $owner->save();
    $variables = $this->preprocess($owner);

    $submitted = $this->renderSubmitted($variables);
    $this->assertStringContainsString('Jane Doe', $submitted);
    $this->assertStringNotContainsString('jdoe', $submitted);
    $this->assertStringContainsString('Jane Doe', (string) $variables['comment_aria_label']);
    $this->assertSame('Jane Doe', $variables['author']);
  }

  /**
   * Without a display name, the comment falls back to the account name.
   */
  public function testFallsBackToAccountName(): void {
    $owner = User::create(['name' => 'jdoe']);
    $owner->save();
    $variables = $this->preprocess($owner);

    $this->assertStringContainsString('jdoe', $this->renderSubmitted($variables));
    $this->assertStringContainsString('jdoe', (string) $variables['comment_aria_label']);
  }

  /**
   * A cached comment is invalidated when its author's display name changes.
   */
  public function testCarriesOwnerCacheTag(): void {
    $owner = User::create(['name' => 'jdoe', 'field_display_name' => 'Jane Doe']);
    $owner->save();
    $variables = $this->preprocess($owner);

    $this->assertIsArray($variables['submitted']);
    $this->assertContains('user:' . $owner->id(), $variables['submitted']['#cache']['tags'] ?? []);
  }

}
