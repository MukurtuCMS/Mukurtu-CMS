<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multilingual\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\comment\Entity\Comment;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\node\Entity\Node;
use PHPUnit\Framework\Attributes\Group;

/**
 * Regression test: saving a content translation on protocol-controlled,
 * commented, moderated content.
 *
 * Every translation save was rejected by core's
 * EntityUntranslatableFieldsConstraintValidator ("Non-translatable fields
 * can only be changed when updating the original language"), because two
 * non-translatable widgets silently mutated their field's value on every
 * submit, including on the translation form where they're supposed to be
 * inert:
 *
 * - CulturalProtocolWidget::massageFormValues() merged the protocol
 *   checkboxes' array *values* instead of their *keys*. That happened to
 *   work when the widget is genuinely rendered (a checked checkbox submits
 *   its own ID as the value), but content_translation hides non-translatable
 *   widgets on translation forms, and Drupal's Form API falls back to raw
 *   PHP booleans as the "submitted" value for a hidden element instead of
 *   the real ID - collapsing every selected protocol down to a single
 *   bogus ID of 1.
 * - mukurtu_multilingual_install() deliberately makes the 'comment' field
 *   non-translatable (its statistics shouldn't diverge per language), which
 *   exposes core's own CommentWidget::massageFormValues(), which always
 *   zeroes those statistics on submit expecting comment.module's save-time
 *   bookkeeping to restore them - bookkeeping that runs after validation,
 *   too late to stop the mismatch.
 *
 * See CulturalProtocolWidget::massageFormValues() and
 * mukurtu_multilingual's CommentTranslationHooks for the fixes.
 */
#[Group('mukurtu_multilingual')]
class TranslationSaveWithProtocolsAndCommentsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mukurtu_multilingual', 'comment'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $profile = 'mukurtu';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    ConfigurableLanguage::createFromLangcode('fr')->save();
  }

  /**
   * A translation should save on content with a real cultural protocol
   * (spanning two communities) and an existing comment.
   */
  public function testTranslationSaveWithProtocolsAndComments(): void {
    $admin = $this->rootUser;

    $community1 = Community::create(['name' => 'Community 1']);
    $community1->save();
    $community1->addMember($admin);

    $community2 = Community::create(['name' => 'Community 2']);
    $community2->save();
    $community2->addMember($admin);

    $protocol1 = Protocol::create([
      'name' => 'Community 1 Protocol',
      'field_communities' => [$community1->id()],
      'field_access_mode' => 'open',
    ]);
    $protocol1->save();
    $protocol1->addMember($admin, ['protocol_steward']);
    $protocol1->setCommentStatus(TRUE);
    $protocol1->save();

    $protocol2 = Protocol::create([
      'name' => 'Community 2 Protocol',
      'field_communities' => [$community2->id()],
      'field_access_mode' => 'open',
    ]);
    $protocol2->save();
    $protocol2->addMember($admin, ['protocol_steward']);

    \Drupal::configFactory()->getEditable('mukurtu_protocol.comment_settings')
      ->set('site_comments_enabled', 1)
      ->save();

    $this->drupalLogin($admin);

    $content = Node::create([
      'title' => 'Original Title',
      'type' => 'digital_heritage',
      'status' => TRUE,
      'uid' => $admin->id(),
    ]);
    $content->setProtocols([$protocol1, $protocol2]);
    $content->setSharingSetting('any');
    $content->save();

    Comment::create([
      'entity_type' => 'node',
      'entity_id' => $content->id(),
      'field_name' => 'comment',
      'comment_type' => 'comment',
      'subject' => 'A comment',
      'comment_body' => ['value' => 'Hello', 'format' => 'basic_html'],
      'uid' => $admin->id(),
      'status' => 1,
    ])->save();

    $this->drupalGet("/node/{$content->id()}/translations/add/en/fr");
    $this->submitForm(
      ['title[0][value]' => 'Titre francais'],
      'Save (this translation)',
    );

    $assert = $this->assertSession();
    $assert->pageTextNotContains('Non-translatable fields can only be changed');
    $assert->pageTextContains('has been updated');

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$content->id()]);
    $reloaded = $storage->load($content->id());

    $this->assertTrue($reloaded->hasTranslation('fr'));
    $this->assertEquals('Titre francais', $reloaded->getTranslation('fr')->label());

    $protocol_ids = $reloaded->getProtocols();
    sort($protocol_ids);
    $expected = [(int) $protocol1->id(), (int) $protocol2->id()];
    sort($expected);
    $this->assertEquals($expected, $protocol_ids);

    $comment_values = $reloaded->get('comment')->first()->getValue();
    $this->assertEquals(1, (int) $comment_values['comment_count']);
  }

}
