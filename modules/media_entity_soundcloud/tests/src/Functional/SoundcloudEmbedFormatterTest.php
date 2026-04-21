<?php

namespace Drupal\Tests\media_entity_soundcloud\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Tests for Soundcloud embed formatter.
 *
 * @group media_entity_soundcloud
 */
class SoundcloudEmbedFormatterTest extends BrowserTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media_entity_soundcloud',
    'media',
    'link',
  ];

  /**
   * {@inheritDoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Setup standalone media urls from the settings.
    $this->config('media.settings')->set('standalone_url', TRUE)->save();
    $this->refreshVariables();
    // Rebuild routes.
    \Drupal::service('router.builder')->rebuild();

    // Create an admin user with permissions to administer and create media.
    $account = $this->drupalCreateUser([
      // Media entity permissions.
      'view media',
      'create media',
      'update media',
      'update any media',
      'delete media',
      'delete any media',
    ]);

    // Login the user.
    $this->drupalLogin($account);
  }

  /**
   * Tests adding and editing a soundcloud embed formatter.
   */
  public function testSoundcloudEmbedFormatter() {
    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface */
    $entity_display_repository = \Drupal::service('entity_display.repository');

    $media_type = $this->createMediaType('soundcloud', ['id' => 'soundcloud']);

    $source_field = $media_type->getSource()->getSourceFieldDefinition($media_type);
    $this->assertSame('field_media_soundcloud', $source_field->getName());
    $this->assertSame('string', $source_field->getType());

    // Set form and view displays.
    \Drupal::service('entity_display.repository')->getFormDisplay('media', $media_type->id(), 'default')
      ->setComponent('field_media_soundcloud', [
        'type' => 'string_textfield',
      ])
      ->save();

    \Drupal::service('entity_display.repository')->getViewDisplay('media', $media_type->id(), 'full')
      ->setComponent('field_media_soundcloud', [
        'type' => 'soundcloud_embed',
      ])
      ->save();

    // Create a soundcloud media entity.
    $this->drupalGet('media/add/' . $media_type->id());

    $page = $this->getSession()->getPage();
    $page->fillField('name[0][value]', 'Soundcloud');
    $page->fillField('field_media_soundcloud[0][value]', 'https://soundcloud.com/pooriaputak/laelaha');
    $page->pressButton('Save');

    // Assert "has been created" text.
    $assert = $this->assertSession();
    $assert->pageTextContains('has been created');

    // Get to the media page.
    $medias = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties([
      'name' => 'Soundcloud',
    ]);
    /** @var \Drupal\media\MediaInterface */
    $media = reset($medias);
    $this->drupalGet(Url::fromRoute('entity.media.canonical', ['media' => $media->id()])->toString());
    $assert->statusCodeEquals(200);

    // Assert that the formatter exists on this page.
    $assert->elementExists('css', 'iframe[src*="soundcloud"]');
  }

}
