<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormState;
use Drupal\entity_browser\Entity\EntityBrowser;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\mukurtu_submissions\Entity\SubmissionSettings;
use Drupal\mukurtu_submissions\Form\PublicSubmissionForm;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that a media (or entity_browser) field nested inside a paragraph -
 * e.g. dictionary_word's sample-sentence recording, field_sentence_recording
 * on the sample_sentence paragraph type, reached via field_sample_sentences -
 * gets the exact same submission-form handling as a field living directly on
 * the submitted bundle: SimpleMediaUploadWidget substitution, a provisioned
 * "submission" display for the paragraph bundle, the referencing field's
 * "form_display_mode" pointed at it, entity_browser access sync, and real
 * Media entity creation from the raw uploaded file on final submit.
 *
 * Before this fix, none of SubmissionFormDisplayManager, PublicSubmissionForm,
 * or mukurtu_submissions_sync_entity_browser_permissions() ever looked past
 * the target bundle's own top-level fields, so a paragraph-nested field kept
 * whatever its "default" display used (normally the full Media Library
 * modal) - unusable by an anonymous visitor, who lacks "view media" access.
 */
#[Group('mukurtu_submissions')]
class ParagraphSubmissionFormTest extends MukurtuSubmissionsKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * Adds paragraphs/entity_reference_revisions/media/entity_browser on top
   * of the shared base list - none of this module's other tests need a
   * real paragraph or media entity type, or config schema for the
   * entity_browser widget, so they're kept out of the shared base class.
   */
  protected static $modules = [
    'field',
    'field_group',
    'file',
    'options',
    'path_alias',
    'node',
    'views',
    'entity_reference_revisions',
    'paragraphs',
    'image',
    'media',
    'entity_browser',
    'mukurtu_submissions',
  ];

  const PARAGRAPH_BUNDLE = 'submission_test_paragraph';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('media');
    $this->installEntitySchema('file');
    $this->installConfig(['media']);

    ParagraphsType::create(['id' => static::PARAGRAPH_BUNDLE, 'label' => 'Test Paragraph'])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_test_recording',
      'entity_type' => 'paragraph',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'media'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_test_recording',
      'entity_type' => 'paragraph',
      'bundle' => static::PARAGRAPH_BUNDLE,
      'label' => 'Test Recording',
      'settings' => ['handler_settings' => ['target_bundles' => ['audio' => 'audio']]],
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_test_paragraphs',
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'settings' => ['target_type' => 'paragraph'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_test_paragraphs',
      'entity_type' => 'node',
      'bundle' => static::TEST_BUNDLE,
      'label' => 'Test Paragraphs',
      'settings' => ['handler_settings' => ['target_bundles' => [static::PARAGRAPH_BUNDLE => static::PARAGRAPH_BUNDLE]]],
    ])->save();

    $default_display = $this->container->get('entity_display.repository')->getFormDisplay('node', static::TEST_BUNDLE, 'default');
    $default_display->setComponent('field_test_paragraphs', ['type' => 'entity_reference_paragraphs']);
    $default_display->save();
  }

  protected function formDisplayManager() {
    return $this->container->get('mukurtu_submissions.form_display_manager');
  }

  public function testParagraphNestedMediaFieldGetsSimpleUploadWidget(): void {
    $settings = SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
    ]);

    $this->formDisplayManager()->ensureSubmissionFormDisplay($settings);

    $node_display = $this->container->get('entity_display.repository')->getFormDisplay('node', static::TEST_BUNDLE, 'submission');
    $component = $node_display->getComponent('field_test_paragraphs');
    $this->assertSame('submission', $component['settings']['form_display_mode'] ?? NULL, 'The paragraph-referencing field is switched to render its items through the "submission" display mode.');

    $paragraph_display = $this->container->get('entity_display.repository')->getFormDisplay('paragraph', static::PARAGRAPH_BUNDLE, 'submission');
    $this->assertFalse($paragraph_display->isNew(), 'A "submission" display was provisioned for the paragraph bundle.');
    $recording_component = $paragraph_display->getComponent('field_test_recording');
    $this->assertSame('mukurtu_simple_media_upload', $recording_component['type'] ?? NULL, 'The paragraph-nested media field got the anonymous-safe simple upload widget, not the Media Library picker.');
  }

  public function testRetrofitAddsHandlingToAlreadyProvisionedDisplay(): void {
    // Simulate a pre-existing site: a "submission" display already exists
    // (provisioned before this fix), still pointing the paragraph field at
    // the "default" form_display_mode.
    $node_display = $this->container->get('entity_display.repository')->getFormDisplay('node', static::TEST_BUNDLE, 'submission');
    $node_display->setComponent('field_test_paragraphs', [
      'type' => 'entity_reference_paragraphs',
      'settings' => ['form_display_mode' => 'default'],
    ]);
    $node_display->save();

    $this->formDisplayManager()->retrofitParagraphSubmissionMode('node', static::TEST_BUNDLE);

    $node_display = $this->container->get('entity_display.repository')->getFormDisplay('node', static::TEST_BUNDLE, 'submission');
    $component = $node_display->getComponent('field_test_paragraphs');
    $this->assertSame('submission', $component['settings']['form_display_mode'] ?? NULL);

    $paragraph_display = $this->container->get('entity_display.repository')->getFormDisplay('paragraph', static::PARAGRAPH_BUNDLE, 'submission');
    $this->assertFalse($paragraph_display->isNew());
  }

  public function testCreateUploadedMediaConvertsNestedRawFidToRealMedia(): void {
    $this->installEntitySchema('node');
    $this->installEntitySchema('mukurtu_submission_settings');
    $this->installSchema('file', ['file_usage']);
    // createMediaFromUpload() elevates to uid 1 to create media on an
    // anonymous visitor's behalf (see its own docblock) - needs a real
    // uid-1 account to switch to, same as a real site always has one.
    $this->createUser([], NULL, FALSE, ['uid' => 1]);

    $settings = SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
    ]);
    $this->formDisplayManager()->ensureSubmissionFormDisplay($settings);

    \Drupal\media\Entity\MediaType::create([
      'id' => 'audio',
      'label' => 'Audio',
      'source' => 'audio_file',
      'source_configuration' => ['source_field' => 'field_media_audio_file'],
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_media_audio_file',
      'entity_type' => 'media',
      'type' => 'file',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_media_audio_file',
      'entity_type' => 'media',
      'bundle' => 'audio',
      'label' => 'Audio file',
    ])->save();

    $uri = $this->container->get('file_system')->saveData('id3', 'public://test-recording.mp3', FileSystemInterface::EXISTS_REPLACE);
    $file = File::create(['uri' => $uri, 'status' => 0]);
    $file->save();

    $paragraph = Paragraph::create(['type' => static::PARAGRAPH_BUNDLE]);
    $node = Node::create(['type' => static::TEST_BUNDLE, 'title' => 'Test']);
    $node->set('field_test_paragraphs', [$paragraph]);

    $form_state = new FormState();
    $form_state->setValues([
      'field_test_paragraphs' => [
        0 => ['subform' => ['field_test_recording' => ['upload' => ['fids' => (string) $file->id()]]]],
      ],
    ]);

    $form_object = $this->container->get('class_resolver')->getInstanceFromDefinition(PublicSubmissionForm::class);
    $display = $this->container->get('entity_display.repository')->getFormDisplay('node', static::TEST_BUNDLE, 'submission');

    $method = new \ReflectionMethod($form_object, 'createUploadedMediaOnEntity');
    $method->setAccessible(TRUE);
    $method->invoke($form_object, $node, $display, [], $form_state, 0);

    $target_id = $paragraph->get('field_test_recording')->target_id;
    $this->assertNotEmpty($target_id);

    // File and Media are different entity types with independent ID
    // sequences, so comparing raw ids for (in)equality would be
    // meaningless (and, coincidentally, sometimes equal) - loading a real,
    // distinct "media" entity referencing the uploaded file is the actual
    // proof this is a real media entity and not the raw upload passed
    // straight through.
    $media = $this->container->get('entity_type.manager')->getStorage('media')->load($target_id);
    $this->assertNotNull($media);
    $this->assertSame('audio', $media->bundle());
    $this->assertFalse($media->isPublished());
    $this->assertSame($file->id(), $media->get('field_media_audio_file')->target_id, 'The created media entity wraps the originally uploaded file.');
  }

  public function testEntityBrowserFieldNestedInParagraphGetsPermissionSynced(): void {
    EntityBrowser::create([
      'name' => 'mukurtu_test_paragraph_browser',
      'label' => 'Test Paragraph Browser',
      'display' => 'modal',
      'display_configuration' => [],
      'selection_display' => 'no_display',
      'selection_display_configuration' => [],
      'widget_selector' => 'single',
      'widget_selector_configuration' => [],
      'widgets' => [],
    ])->save();

    $settings = SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
    ]);
    $this->formDisplayManager()->ensureSubmissionFormDisplay($settings);

    $paragraph_display = $this->container->get('entity_display.repository')->getFormDisplay('paragraph', static::PARAGRAPH_BUNDLE, 'submission');
    $paragraph_display->setComponent('field_test_recording', [
      'type' => 'entity_browser_entity_reference',
      'settings' => ['entity_browser' => 'mukurtu_test_paragraph_browser'],
    ]);
    $paragraph_display->save();

    $settings->set('status', TRUE)->set('access_level', 'anonymous')->save();

    $this->assertTrue(Role::load('anonymous')->hasPermission('access mukurtu_test_paragraph_browser entity browser pages'), 'Anonymous is granted access to an entity browser used only by a paragraph-nested field.');
  }

}
