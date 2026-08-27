<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\message\Entity\Message;
use Drupal\message\Entity\MessageTemplate;
use Drupal\node\Entity\Node;

/**
 * Regression guard for the mukurtu_submission_received template's link
 * bug: a chained token directly inside an href
 * ("<a href=\"[message:field_item:entity:url]\">") gets mangled by the
 * text format's filters before token replacement ever runs (see
 * mukurtu_notifications_tokens() for the full explanation) - the fixed
 * template uses the safe mukurtu-notification-link token instead (see
 * mukurtu_submissions_update_40011()).
 *
 * Calls Message::getText($langcode, $delta) directly rather than
 * rendering through the entity view builder ('mail_subject'/'mail_body'
 * view modes) - that pipeline invokes every module's
 * hook_ENTITY_TYPE_view_alter(), and mukurtu_notifications' own
 * (unrelated to what's under test here) alter hook requires services
 * from its heavier dependencies (e.g. 'flag') that aren't set up in this
 * lightweight test. getText() does the exact thing the bug is about -
 * running the template's own filter format, then token replacement -
 * without any of that. 'mukurtu_notifications' is still enabled, only
 * for its hook_tokens() implementation that provides the safe token
 * being tested; KernelTestBase's $modules mechanism never runs that
 * module's own hook_install(), so none of its other dependencies
 * (mukurtu_collection, mukurtu_protocol, og, flag, etc.) are needed for
 * it to be reachable here.
 *
 * Uses core's basic_html format (not mukurtu_html) for the body delta -
 * the bug is in core's own filter_html behavior, not anything
 * mukurtu_html-specific, so this stays independent of that format's own
 * config.
 *
 * @group mukurtu_submissions
 */
class SubmissionReceivedNotificationRenderingTest extends MukurtuSubmissionsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'message',
    'filter',
    'mukurtu_notifications',
    'token',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['filter', 'system']);
    $this->installEntitySchema('message');
    $this->installSchema('node', ['node_access']);

    // A minimal filter_html format, not a named profile-shipped one like
    // basic_html (this isn't the 'standard' profile, so that doesn't
    // exist here) - filter_html is the specific filter responsible for
    // the href-stripping bug being guarded against, so this is the exact
    // mechanism under test, independent of which real format a site uses.
    FilterFormat::create([
      'format' => 'test_html_filter',
      'name' => 'Test HTML filter',
      'filters' => [
        'filter_html' => [
          'status' => TRUE,
          'settings' => ['allowed_html' => '<p> <a href>'],
        ],
      ],
    ])->save();

    MessageTemplate::create([
      'template' => 'mukurtu_submission_received',
      'label' => 'Mukurtu Submission Received',
      'text' => [
        [
          'value' => 'New [message:field_item:entity:content-type] submitted for review: [message:field_item:entity:title]',
          'format' => 'plain_text',
        ],
        [
          'value' => '<p>A new [message:field_item:entity:content-type] was submitted for review: [message:field_item:entity:mukurtu-notification-link].</p>',
          'format' => 'test_html_filter',
        ],
      ],
      'settings' => [
        'token options' => ['clear' => TRUE, 'token replace' => TRUE],
      ],
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_item',
      'entity_type' => 'message',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_item',
      'entity_type' => 'message',
      'bundle' => 'mukurtu_submission_received',
      'settings' => ['handler' => 'default:node'],
    ])->save();
  }

  protected function createTestMessage(Node $node): Message {
    $message = Message::create(['template' => 'mukurtu_submission_received']);
    $message->set('field_item', $node);
    $message->save();
    return $message;
  }

  public function testBodyDeltaContainsARealLinkNotAMangledToken(): void {
    $node = Node::create([
      'type' => static::TEST_BUNDLE,
      'title' => 'Regression Guard Node',
      // Unpublished: mukurtu_notifications' own node-insert hooks (enabled
      // here only for its unrelated hook_tokens()) skip unpublished nodes
      // entirely, avoiding side effects this test isn't set up for.
      'status' => 0,
    ]);
    $node->save();
    $message = $this->createTestMessage($node);

    $body = $message->getText(NULL, 1);
    $rendered = is_array($body) ? implode('', $body) : (string) $body;

    $this->assertStringNotContainsString('url]', $rendered);
    $this->assertStringContainsString($node->toUrl()->setAbsolute()->toString(), $rendered);
    $this->assertStringContainsString('Regression Guard Node', $rendered);
  }

  public function testSubjectDeltaIsPlainTextWithNoLink(): void {
    $node = Node::create([
      'type' => static::TEST_BUNDLE,
      'title' => 'Regression Guard Node',
      // Unpublished: mukurtu_notifications' own node-insert hooks (enabled
      // here only for its unrelated hook_tokens()) skip unpublished nodes
      // entirely, avoiding side effects this test isn't set up for.
      'status' => 0,
    ]);
    $node->save();
    $message = $this->createTestMessage($node);

    $subject = $message->getText(NULL, 0);
    $rendered = is_array($subject) ? implode('', $subject) : (string) $subject;

    $this->assertStringContainsString('Regression Guard Node', $rendered);
    $this->assertStringNotContainsString('<a', $rendered);
  }

}
