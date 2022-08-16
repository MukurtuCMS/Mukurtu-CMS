<?php

namespace Drupal\Tests\mukurtu_protocol\Functional;

use Drupal\Tests\mukurtu_protocol\Functional\ProtocolAwareFunctionalTestBase;

/**
 * Tests Mukurtu protocol aware comments.
 *
 * @group mukurtu_protocol
 */
class ProtocolAwareCommentsTest extends ProtocolAwareFunctionalTestBase {

  /**
   * Enable site wide commenting.
   *
   * @return void
   */
  protected function enableSiteWideComments() {
    $configFactory = \Drupal::service('config.factory');
    $configFactory->getEditable('mukurtu_protocol.comment_settings')
      ->set('site_comments_enabled', 1)
      ->save();
  }

  /**
   * Disable site wide commenting.
   *
   * @return void
   */
  protected function disableSiteWideComments() {
    $configFactory = \Drupal::service('config.factory');
    $configFactory->getEditable('mukurtu_protocol.comment_settings')
    ->set('site_comments_enabled', 0)
    ->save();
  }

  /**
   * Test if site wide comment disable setting is respected.
   */
  public function testSiteWideCommentsDisabled() {
    // Disable site wide comments.
    $this->disableSiteWideComments();

    // Enable comments for protocol. This shouldn't matter.
    // Site wide should still take precedence.
    $this->community1_open->setCommentStatus(TRUE);
    $this->community1_open->save();

    $content = $this->mukurtuCreateNode([
      'title' => $this->randomString(),
      'type' => 'digital_heritage',
      'status' => TRUE,
      'uid' => 1,
    ], [$this->community1_open], 'any');

    $account = $this->drupalCreateUser([]);
    $this->drupalLogin($account);

    $this->drupalGet("/node/{$content->id()}");
    $sessionAssert = $this->assertSession();
    $sessionAssert->statusCodeEquals(200);
    $sessionAssert->pageTextNotContains('Add new comment');
  }

  /**
   * Test a single protocol with comments enabled.
   */
  public function testOneProtocolCommentsEnabled() {
    $this->enableSiteWideComments();

    // Enable comments for protocol.
    $this->community1_open->setCommentStatus(TRUE);
    $this->community1_open->save();

    $content = $this->mukurtuCreateNode([
      'title' => $this->randomString(),
      'type' => 'digital_heritage',
      'status' => TRUE,
      'uid' => 1,
    ], [$this->community1_open], 'any');

    $account = $this->drupalCreateUser([]);
    $this->drupalLogin($account);

    $this->drupalGet("/node/{$content->id()}");
    $sessionAssert = $this->assertSession();
    $sessionAssert->statusCodeEquals(200);
    $sessionAssert->pageTextContainsOnce('Add new comment');
  }

  /**
   * Test a single protocol with comments disabled.
   */
  public function testOneProtocolCommentsDisabled() {
    $this->enableSiteWideComments();

    // Disable comments for protocol.
    $this->community1_open->setCommentStatus(FALSE);
    $this->community1_open->save();

    $content = $this->mukurtuCreateNode([
      'title' => $this->randomString(),
      'type' => 'digital_heritage',
      'status' => TRUE,
      'uid' => 1,
    ], [$this->community1_open], 'any');

    $account = $this->drupalCreateUser([]);
    $this->drupalLogin($account);

    $this->drupalGet("/node/{$content->id()}");
    $sessionAssert = $this->assertSession();
    $sessionAssert->statusCodeEquals(200);
    $sessionAssert->pageTextNotContains('Add new comment');
  }

  /**
   * Test two protocols, both with comments enabled.
   */
  public function testTwoProtocolsBothCommentsEnabled() {
    $this->enableSiteWideComments();

    // Enable comments for one protocol.
    $this->community1_open->setCommentStatus(TRUE);
    $this->community1_open->save();

    // Disable for the other.
    $this->community2_open->setCommentStatus(TRUE);
    $this->community2_open->save();

    $content = $this->mukurtuCreateNode([
      'title' => $this->randomString(),
      'type' => 'digital_heritage',
      'status' => TRUE,
      'uid' => 1,
    ], [$this->community1_open, $this->community2_open], 'any');

    $account = $this->drupalCreateUser([]);
    $this->drupalLogin($account);

    $this->drupalGet("/node/{$content->id()}");
    $sessionAssert = $this->assertSession();
    $sessionAssert->statusCodeEquals(200);
    $sessionAssert->pageTextContainsOnce('Add new comment');
  }

  /**
   * Test two protocols, one with comments disabled.
   */
  public function testTwoProtocolsOneCommentsDisabled() {
    $this->enableSiteWideComments();

    // Enable comments for one protocol.
    $this->community1_open->setCommentStatus(TRUE);
    $this->community1_open->save();

    // Disable for the other.
    $this->community2_open->setCommentStatus(FALSE);
    $this->community2_open->save();

    $content = $this->mukurtuCreateNode([
      'title' => $this->randomString(),
      'type' => 'digital_heritage',
      'status' => TRUE,
      'uid' => 1,
    ], [$this->community1_open, $this->community2_open], 'any');

    $account = $this->drupalCreateUser([]);
    $this->drupalLogin($account);

    $this->drupalGet("/node/{$content->id()}");
    $sessionAssert = $this->assertSession();
    $sessionAssert->statusCodeEquals(200);
    $sessionAssert->pageTextNotContains('Add new comment');
  }

}
