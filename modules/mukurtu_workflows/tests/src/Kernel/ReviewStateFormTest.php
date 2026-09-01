<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_workflows\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use Drupal\mukurtu_protocol\Plugin\Field\FieldType\CulturalProtocolItem;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that ReviewStateForm enforces the same required-field validation
 * the full node edit form already does, and that a valid transition still
 * works normally.
 *
 * ReviewStateForm's submitForm() saves a node directly ($node->save()),
 * bypassing EntityFormDisplay entirely - a plain save never validates on
 * its own, so without an explicit validate() call, a node could reach the
 * "published" moderation state without a required Cultural Protocol.
 */
#[Group('mukurtu_workflows')]
class ReviewStateFormTest extends ProtocolAwareEntityTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'mukurtu_workflows',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('content_moderation_state');
    $this->installSchema('mukurtu_workflows', ['mukurtu_review_note']);

    $workflow = $this->createEditorialWorkflow();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', 'protocol_aware_content');

    // Grant the transitions used below directly - this test is about the
    // validation gap, not protocol-level transition permissions (already
    // covered by ProtocolAwareStateTransitionValidation's own tests).
    $role = Role::load('authenticated');
    $role->grantPermission('use editorial transition create_new_draft');
    $role->grantPermission('use editorial transition publish');
    $role->save();
  }

  /**
   * Builds and runs ReviewStateForm exactly as Form API would: validateForm()
   * then, only if that added no errors, submitForm().
   */
  protected function runReviewStateForm(Node $node, string $to_state, string $note = ''): FormState {
    $form_state = (new FormState())->setValues([
      'node_id' => $node->id(),
      'from_state' => $node->get('moderation_state')->value,
      'note' => $note,
    ]);
    $form_state->setTriggeringElement(['#mukurtu_to_state' => $to_state]);

    $form_object = \Drupal::classResolver(\Drupal\mukurtu_workflows\Form\ReviewStateForm::class);
    $form = [];
    $form_object->validateForm($form, $form_state);
    if (!$form_state->getErrors()) {
      $form_object->submitForm($form, $form_state);
    }

    return $form_state;
  }

  /**
   * A node with no Cultural Protocol assigned cannot be published through
   * the quick-publish review panel - it must fail the same way the full
   * edit form already would, not save silently.
   */
  public function testPublishWithoutProtocolIsBlocked(): void {
    $node = Node::create([
      'type' => 'protocol_aware_content',
      'title' => $this->randomString(),
      'uid' => $this->currentUser->id(),
      'moderation_state' => 'draft',
    ]);
    $node->save();

    $form_state = $this->runReviewStateForm($node, 'published', 'attempted note');

    $this->assertNotEmpty($form_state->getErrors(), 'Validation failure should produce a form error.');
    // The note the reviewer typed must survive the failed attempt rather
    // than being silently discarded (WCAG 3.3.7).
    $this->assertEquals('attempted note', $form_state->getValue('note'));

    $node = Node::load($node->id());
    $this->assertEquals('draft', $node->get('moderation_state')->value);
    $this->assertFalse($node->isPublished());
  }

  /**
   * The validation error must name the actual missing field - $node->
   * validate()'s own message for an empty required field ("This value
   * should not be null.") never says which field, leaving a reviewer with
   * no way to tell what's wrong. Confirmed live: this exact scenario
   * (publishing a pending submission with no protocol assigned) was the
   * bug report this fix addresses.
   */
  public function testPublishWithoutProtocolNamesTheMissingField(): void {
    $node = Node::create([
      'type' => 'protocol_aware_content',
      'title' => $this->randomString(),
      'uid' => $this->currentUser->id(),
      'moderation_state' => 'draft',
    ]);
    $node->save();

    $form_state = $this->runReviewStateForm($node, 'published');

    $errors = $form_state->getErrors();
    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('Cultural Protocols are required.', (string) reset($errors));
    $this->assertStringNotContainsString('This value should not be null.', (string) reset($errors));
  }

  /**
   * A node with a Cultural Protocol assigned publishes normally through the
   * same review panel - the new validation must not block legitimate,
   * fully-populated content.
   */
  public function testPublishWithProtocolSucceeds(): void {
    $node = Node::create([
      'type' => 'protocol_aware_content',
      'title' => $this->randomString(),
      'uid' => $this->currentUser->id(),
      'moderation_state' => 'draft',
    ]);
    $node->set('field_cultural_protocols', [
      'protocols' => CulturalProtocolItem::formatProtocols([1]),
      'sharing_setting' => 'all',
    ]);
    $node->save();

    $form_state = $this->runReviewStateForm($node, 'published', 'looks good');

    $this->assertEmpty($form_state->getErrors());

    $node = Node::load($node->id());
    $this->assertEquals('published', $node->get('moderation_state')->value);
    $this->assertTrue($node->isPublished());

    $note = \Drupal::database()->select('mukurtu_review_note', 'r')
      ->fields('r', ['note'])
      ->condition('nid', $node->id())
      ->execute()
      ->fetchField();
    $this->assertEquals('looks good', $note);
  }

}
