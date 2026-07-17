<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_notifications\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\Message;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\views\Views;

/**
 * Tests that the personal notifications feed cannot leak another user's
 * messages via the "uid" contextual filter.
 *
 * Regression test for a privacy bug: the "notifications" page's uid argument
 * had no validation, so /notifications/<uid> would use whatever uid was
 * supplied in the URL instead of the current user -- see
 * mukurtu_notifications_views_pre_view().
 *
 * @group mukurtu_notifications
 */
class NotificationsFeedAccessTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'comment',
    'field',
    'flag',
    'layout_builder',
    'message',
    'message_notify',
    'message_subscribe',
    'message_subscribe_email',
    'message_ui',
    'node',
    'og',
    'options',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'mukurtu_collection',
    'mukurtu_core',
    'mukurtu_notifications',
    'mukurtu_protocol',
  ];

  protected User $userA;
  protected User $userB;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('message');
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('system', ['sequences']);

    $this->installConfig(['message', 'mukurtu_notifications']);

    // uid 1 is created implicitly and bypasses permission checks -- burn it
    // on a throwaway user first so the real test users below aren't
    // accidentally uid 1 (see ProtocolAwareEntityTestBase for this gotcha).
    User::create(['name' => 'throwaway'])->save();

    Role::create(['id' => 'authenticated', 'label' => 'Authenticated'])->save();

    $this->userA = User::create(['name' => 'user_a']);
    $this->userA->save();

    $this->userB = User::create(['name' => 'user_b']);
    $this->userB->save();

    // One message owned by each user, so a leak is detectable by content,
    // not just by row count.
    Message::create([
      'template' => 'mukurtu_user_deleted',
      'uid' => $this->userA->id(),
      'field_title' => 'A was deleted',
    ])->save();
    Message::create([
      'template' => 'mukurtu_user_deleted',
      'uid' => $this->userB->id(),
      'field_title' => 'B was deleted',
    ])->save();
  }

  /**
   * Loading the personal feed with another user's uid as the URL argument
   * must not use that uid -- mukurtu_notifications_views_pre_view() should
   * overwrite it with the current user's own uid before the query runs.
   */
  public function testCannotViewAnotherUsersFeedViaArgument(): void {
    $this->container->get('current_user')->setAccount($this->userA);

    $view = Views::getView('mukurtu_message_log');
    $view->setDisplay('mukurtu_notifications_page');
    // Attempt to view user B's feed by supplying their uid as the argument.
    $view->setArguments([(string) $this->userB->id()]);
    $view->execute();

    // The fix works by rewriting the argument itself before the query is
    // built -- assert the mechanism directly: whatever was supplied in the
    // URL, the view's actual argument after execution is the current user's
    // uid, not the one that was requested.
    $this->assertSame(
      (string) $this->userA->id(),
      $view->args[0],
      'The uid argument must be forced to the current user regardless of what was supplied in the URL.'
    );
    $this->assertNotSame(
      (string) $this->userB->id(),
      $view->args[0],
      'The uid argument must never be left as another user\'s uid.'
    );

    $view->destroy();
  }

  /**
   * With no argument supplied at all, the page should default to the
   * current user's own feed (the pre-existing, already-correct behavior
   * this fix must not regress).
   */
  public function testDefaultsToOwnFeedWithNoArgument(): void {
    $this->container->get('current_user')->setAccount($this->userA);

    $view = Views::getView('mukurtu_message_log');
    $view->setDisplay('mukurtu_notifications_page');
    $view->execute();

    $this->assertSame((string) $this->userA->id(), $view->args[0]);

    $view->destroy();
  }

}
