<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_footer\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_footer\Controller\FooterEditRedirectController;
use Drupal\mukurtu_footer\Hook\FormHooks;
use Drupal\paragraphs\Entity\Paragraph;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the MukurtuFooterBlock plugin and mukurtu_footer_update_40001().
 */
#[Group('mukurtu_footer')]
class FooterBlockTest extends KernelTestBase {

  /**
   * testUpdateHookMigratesBlockSettings() seeds a legacy pre-migration block
   * settings fixture (social_media, contact_email_address, etc.) that the
   * current block.settings.mukurtu_footer schema no longer declares, since
   * the plugin's settings moved into a block_content entity. Production code
   * only ever reads that legacy config, never re-saves it, so this is a
   * test-fixture concern, not a real schema gap.
   *
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  protected static $modules = [
    'system',
    'field',
    'block',
    'block_content',
    'user',
    'text',
    'link',
    'filter',
    'options',
    'token',
    'file',
    'image',
    'entity_reference_revisions',
    'paragraphs',
    'mukurtu_footer',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('block_content');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('system', 'sequences');
    $this->installSchema('file', 'file_usage');
    $this->installConfig(['field', 'filter', 'user', 'mukurtu_footer']);
  }

  /**
   * Tests build() cache tags include paragraph entity tags.
   */
  public function testBuildIncludesParagraphCacheTags(): void {
    $social = Paragraph::create([
      'type' => 'footer_social_link',
      'field_footer_social_platform' => 'twitter',
      'field_footer_social_url' => [
        'uri' => 'https://x.com/mukurtucms',
        'title' => 'Mukurtu CMS',
      ],
    ]);
    $social->save();

    $footer = BlockContent::create([
      'type' => 'mukurtu_footer',
      'info' => 'Test Footer',
      'status' => TRUE,
      'field_footer_social_links' => [
        [
          'target_id' => $social->id(),
          'target_revision_id' => $social->getRevisionId(),
        ],
      ],
    ]);
    $footer->save();

    $block = $this->container->get('plugin.manager.block')
      ->createInstance('mukurtu_footer', []);
    $build = $block->build();

    $this->assertNotEmpty($build['#cache']['tags']);
    $this->assertContains('block_content:' . $footer->id(), $build['#cache']['tags']);
    $this->assertContains('paragraph:' . $social->id(), $build['#cache']['tags']);
    $this->assertContains('block_content_list', $build['#cache']['tags']);
  }

  /**
   * Tests build() returns empty array and logs a notice when no entity exists.
   */
  public function testBuildWithNoEntityReturnsEmpty(): void {
    $block = $this->container->get('plugin.manager.block')
      ->createInstance('mukurtu_footer', []);
    $build = $block->build();

    $this->assertSame([], $build);
  }

  /**
   * Tests mukurtu_footer_update_40001() skips when an entity already exists.
   */
  public function testUpdateHookSkipsWhenEntityExists(): void {
    $footer = BlockContent::create([
      'type' => 'mukurtu_footer',
      'info' => 'Existing Footer',
      'status' => TRUE,
    ]);
    $footer->save();

    require_once __DIR__ . '/../../../mukurtu_footer.install';
    mukurtu_footer_update_40001();

    $entities = \Drupal::entityTypeManager()
      ->getStorage('block_content')
      ->loadByProperties(['type' => 'mukurtu_footer']);

    // Still exactly one entity — the hook did not create a duplicate.
    $this->assertCount(1, $entities);
    $this->assertEquals($footer->id(), reset($entities)->id());
  }

  /**
   * Tests mukurtu_footer_update_40001() migrates data from block settings.
   */
  public function testUpdateHookMigratesBlockSettings(): void {
    // Write a block config directly with the old plugin settings format.
    \Drupal::configFactory()
      ->getEditable('block.block.mukurtu_v4_footer_test')
      ->setData([
        'langcode' => 'en',
        'status' => TRUE,
        'id' => 'mukurtu_v4_footer_test',
        'theme' => 'stark',
        'region' => 'footer',
        'weight' => 0,
        'provider' => NULL,
        'plugin' => 'mukurtu_footer',
        'settings' => [
          'id' => 'mukurtu_footer',
          'label' => 'Mukurtu Footer',
          'social_media' => [
            'twitter' => [
              'account_1' => 'mukurtucms',
              'account_2' => '',
              'account_3' => '',
            ],
            'facebook' => [
              'account_1' => '',
              'account_2' => '',
              'account_3' => '',
            ],
            'instagram' => [
              'account_1' => '',
              'account_2' => '',
              'account_3' => '',
            ],
          ],
          'contact_email_address' => 'info@mukurtu.org',
          'email_us_text' => 'Contact us',
          'copyright_message' => '© 2024 Mukurtu CMS',
          'logo_upload' => [],
        ],
        'visibility' => [],
      ])
      ->save();

    require_once __DIR__ . '/../../../mukurtu_footer.install';
    mukurtu_footer_update_40001();

    $entities = \Drupal::entityTypeManager()
      ->getStorage('block_content')
      ->loadByProperties(['type' => 'mukurtu_footer']);

    $this->assertCount(1, $entities);
    $footer = reset($entities);

    $this->assertEquals('info@mukurtu.org', $footer->get('field_footer_contact_email')->value);
    $this->assertEquals('Contact us', $footer->get('field_footer_contact_email_label')->value);
    $this->assertEquals('© 2024 Mukurtu CMS', $footer->get('field_footer_copyright')->value);

    $social_links = $footer->get('field_footer_social_links')->referencedEntities();
    $this->assertCount(1, $social_links, 'Only the non-empty Twitter account should be migrated.');
    $this->assertEquals('twitter', $social_links[0]->get('field_footer_social_platform')->value);
    $this->assertStringContainsString('mukurtucms', $social_links[0]->get('field_footer_social_url')->uri);
  }

  /**
   * FooterEditRedirectController redirects to the footer's actual edit form,
   * regardless of its block_content entity ID.
   */
  public function testEditContentRedirectsToFooterEditForm(): void {
    // Create and delete an unrelated block_content entity first so the
    // footer's ID is not 1, guarding against the hardcoded-path regression
    // this controller replaces.
    $decoy = BlockContent::create(['type' => 'mukurtu_footer', 'info' => 'Decoy']);
    $decoy->save();
    $decoy->delete();

    $footer = BlockContent::create(['type' => 'mukurtu_footer', 'info' => 'Test Footer']);
    $footer->save();
    $this->assertNotSame(1, (int) $footer->id());

    $controller = FooterEditRedirectController::create($this->container);
    $response = $controller->edit();

    $this->assertStringEndsWith('/admin/content/block/' . $footer->id(), $response->getTargetUrl());
  }

  /**
   * FooterEditRedirectController falls back to the block library when no
   * footer block_content entity exists.
   */
  public function testEditContentRedirectsToCollectionWhenNoFooterExists(): void {
    $controller = FooterEditRedirectController::create($this->container);
    $response = $controller->edit();

    $this->assertStringEndsWith('/admin/content/block', $response->getTargetUrl());
  }

  /**
   * The shipped social link URL field has no config-level description; the
   * guidance is supplied by FormHooks::fieldWidgetSingleElementFormAlter()
   * instead, since core's LinkWidget would otherwise attach a config-level
   * description to the outer fieldset (after "Link text") rather than the
   * URL input itself (#2159).
   */
  public function testSocialUrlFieldHasNoConfigLevelDescription(): void {
    $field = FieldConfig::loadByName('paragraph', 'footer_social_link', 'field_footer_social_url');
    $this->assertNotNull($field);
    $this->assertSame('', $field->getDescription());
  }

  /**
   * mukurtu_footer_update_40005() clears a stale config-level description on
   * sites that installed before the hook-based approach existed.
   */
  public function testUpdate40005ClearsSocialUrlFieldDescription(): void {
    $field = FieldConfig::loadByName('paragraph', 'footer_social_link', 'field_footer_social_url');
    $field->setDescription('Start typing the title of a piece of content...')->save();

    require_once __DIR__ . '/../../../mukurtu_footer.install';
    mukurtu_footer_update_40005();

    $updated = FieldConfig::loadByName('paragraph', 'footer_social_link', 'field_footer_social_url');
    $this->assertSame('', $updated->getDescription());
  }

  /**
   * FormHooks::fieldWidgetSingleElementFormAlter() replaces the URL input's
   * description directly for all three footer link fields, since core's
   * placement of a config-level description is either detached from the URL
   * input (title-enabled link fields) or, for multi-value fields, shown only
   * once below every row (#2159).
   */
  public function testFieldWidgetFormAlterReplacesUrlDescription(): void {
    $social = Paragraph::create(['type' => 'footer_social_link']);
    $social->save();
    $logo = Paragraph::create(['type' => 'footer_logo']);
    $logo->save();
    $footer = BlockContent::create(['type' => 'mukurtu_footer', 'info' => 'Test Footer']);
    $footer->save();

    $cases = [
      [$social->get('field_footer_social_url'), 'platform selected above'],
      [$logo->get('field_footer_logo_link'), 'Wrap the logo'],
      [$footer->get('field_footer_other_links'), 'organizational pages'],
    ];

    $hook = new FormHooks();
    $form_state = new FormState();
    foreach ($cases as [$items, $expected_substring]) {
      $element = ['uri' => ['#description' => 'Start typing the title of a piece of content to select it...']];
      $context = ['items' => $items];
      $hook->fieldWidgetSingleElementFormAlter($element, $form_state, $context);
      $this->assertStringContainsString($expected_substring, (string) $element['uri']['#description']);
      $this->assertStringNotContainsString('Start typing the title', (string) $element['uri']['#description']);
    }
  }

  /**
   * The hook leaves unrelated link fields' descriptions untouched.
   */
  public function testFieldWidgetFormAlterIgnoresUnrelatedFields(): void {
    $footer = BlockContent::create(['type' => 'mukurtu_footer', 'info' => 'Test Footer']);
    $footer->save();

    $element = ['uri' => ['#description' => 'Start typing the title of a piece of content to select it...']];
    $context = ['items' => $footer->get('field_footer_copyright')];

    $hook = new FormHooks();
    $form_state = new FormState();
    $hook->fieldWidgetSingleElementFormAlter($element, $form_state, $context);

    $this->assertSame('Start typing the title of a piece of content to select it...', $element['uri']['#description']);
  }

  /**
   * Field widget descriptions are always run through token replacement
   * (\Drupal\Core\Field\WidgetBase::getFilteredDescription()), so a literal
   * "[current-date:html_year]" in the copyright field's help text would be
   * silently evaluated into the actual year instead of shown as typed. The
   * shipped description escapes the brackets as HTML entities so the token
   * scanner leaves it alone.
   */
  public function testCopyrightFieldDescriptionSurvivesTokenReplacement(): void {
    $field = FieldConfig::loadByName('block_content', 'mukurtu_footer', 'field_footer_copyright');
    $this->assertNotNull($field);

    $description = $field->getDescription();
    $this->assertStringContainsString('&#91;current-date:html_year&#93;', $description);

    $replaced = \Drupal::token()->replace($description);
    $this->assertSame($description, $replaced, 'Token replacement must not alter the escaped description.');
  }

  /**
   * mukurtu_footer_update_40006() re-escapes the token brackets on sites that
   * installed before it existed.
   */
  public function testUpdate40006EscapesCopyrightTokenBrackets(): void {
    $field = FieldConfig::loadByName('block_content', 'mukurtu_footer', 'field_footer_copyright');
    $field->setDescription('Use the token [current-date:html_year] for the current year. Leave empty to hide.')->save();

    require_once __DIR__ . '/../../../mukurtu_footer.install';
    mukurtu_footer_update_40006();

    $updated = FieldConfig::loadByName('block_content', 'mukurtu_footer', 'field_footer_copyright');
    $this->assertStringContainsString('&#91;current-date:html_year&#93;', $updated->getDescription());
  }

  /**
   * The shipped "Other links" and footer logo link fields also have no
   * config-level description, for the same reason as the social URL field
   * (#2159).
   */
  public function testOtherLinksAndLogoLinkFieldsHaveNoConfigLevelDescription(): void {
    $other_links = FieldConfig::loadByName('block_content', 'mukurtu_footer', 'field_footer_other_links');
    $this->assertNotNull($other_links);
    $this->assertSame('', $other_links->getDescription());

    $logo_link = FieldConfig::loadByName('paragraph', 'footer_logo', 'field_footer_logo_link');
    $this->assertNotNull($logo_link);
    $this->assertSame('', $logo_link->getDescription());
  }

  /**
   * mukurtu_footer_update_40007() clears stale config-level descriptions on
   * sites that installed before the hook-based approach existed.
   */
  public function testUpdate40007ClearsOtherLinksAndLogoLinkDescriptions(): void {
    $other_links = FieldConfig::loadByName('block_content', 'mukurtu_footer', 'field_footer_other_links');
    $other_links->setDescription('Links to organizational pages, partners, privacy policy, etc.')->save();
    $logo_link = FieldConfig::loadByName('paragraph', 'footer_logo', 'field_footer_logo_link');
    $logo_link->setDescription('Wrap the logo in a link to this URL.')->save();

    require_once __DIR__ . '/../../../mukurtu_footer.install';
    mukurtu_footer_update_40007();

    $updated_other_links = FieldConfig::loadByName('block_content', 'mukurtu_footer', 'field_footer_other_links');
    $this->assertSame('', $updated_other_links->getDescription());
    $updated_logo_link = FieldConfig::loadByName('paragraph', 'footer_logo', 'field_footer_logo_link');
    $this->assertSame('', $updated_logo_link->getDescription());
  }

}
