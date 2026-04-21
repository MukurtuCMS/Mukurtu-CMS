<?php

namespace Drupal\Tests\twig_tweak\Functional;

use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\media\Entity\Media;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\user\Entity\Role;

/**
 * A test for Twig extension.
 *
 * @group twig_tweak
 */
final class TwigTweakTest extends BrowserTestBase {

  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'twig_tweak',
    'twig_tweak_test',
    'views',
    'node',
    'block',
    'image',
    'responsive_image',
    'language',
    'contextual',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $test_files = $this->getTestFiles('image');

    $image_file = File::create([
      'uri' => $test_files[0]->uri,
      'uuid' => 'b2c22b6f-7bf8-4da4-9de5-316e93487518',
      'status' => FileInterface::STATUS_PERMANENT,
    ]);
    $image_file->save();

    $media_file = File::create([
      'uri' => $test_files[9]->uri,
      'uuid' => '5dd794d0-cb75-4130-9296-838aebc1fe74',
      'status' => FileInterface::STATUS_PERMANENT,
    ]);
    $media_file->save();

    $media = Media::create([
      'bundle' => 'image',
      'name' => 'Image 1',
      'field_media_image' => ['target_id' => $media_file->id()],
    ]);
    $media->save();

    $node_values = [
      'title' => 'Alpha',
      'uuid' => 'ad1b902a-344f-41d1-8c61-a69f0366dbfa',
      'body' => 'The node content.',
      'field_image' => [
        'target_id' => $image_file->id(),
        'alt' => 'Alt text',
        'title' => 'Title',
      ],
      'field_media' => [
        'target_id' => $media->id(),
      ],
    ];

    $this->createNode($node_values);
    $this->createNode(['title' => 'Beta']);
    $this->createNode(['title' => 'Gamma']);

    ResponsiveImageStyle::create([
      'id' => 'example',
      'label' => 'Example',
      'breakpoint_group' => 'responsive_image',
    ])->save();

    // Setup Russian language.
    ConfigurableLanguage::createFromLangcode('ru')->save();
  }

  /**
   * Tests output produced by the Twig extension.
   */
  public function testOutput(): void {

    $this->drupalGet('twig-tweak-test');

    // -- View (default display).
    $xpath = '//div[@class = "tt-view-default"]';
    $xpath .= '//div[contains(@class, "view-twig-tweak-test") and contains(@class, "view-display-id-default")]';
    $xpath .= '/div[@class = "view-content"]//ul[count(./li) = 3]/li';
    $this->assertXpath($xpath . '//a[contains(@href, "/node/1") and text() = "Alpha"]');
    $this->assertXpath($xpath . '//a[contains(@href, "/node/2") and text() = "Beta"]');
    $this->assertXpath($xpath . '//a[contains(@href, "/node/3") and text() = "Gamma"]');

    // -- View (page_1 display).
    $xpath = '//div[@class = "tt-view-page_1"]';
    $xpath .= '//div[contains(@class, "view-twig-tweak-test") and contains(@class, "view-display-id-page_1")]';
    $xpath .= '/div[@class = "view-content"]//ul[count(./li) = 3]/li';
    $this->assertXpath($xpath . '//a[contains(@href, "/node/1") and text() = "Alpha"]');
    $this->assertXpath($xpath . '//a[contains(@href, "/node/2") and text() = "Beta"]');
    $this->assertXpath($xpath . '//a[contains(@href, "/node/3") and text() = "Gamma"]');

    // -- View with arguments.
    $xpath = '//div[@class = "tt-view-page_1-with-argument"]';
    $xpath .= '//div[contains(@class, "view-twig-tweak-test")]';
    $xpath .= '/div[@class = "view-content"]//ul[count(./li) = 1]/li';
    $this->assertXpath($xpath . '//a[contains(@href, "/node/1") and text() = "Alpha"]');

    // -- View result.
    $xpath = '//div[@class = "tt-view-result" and text() = 3]';
    $this->assertXpath($xpath);

    // -- Block.
    $xpath = '//div[@class = "tt-block"]';
    $xpath .= '/img[contains(@src, "/core/themes/claro/logo.svg") and @alt="Home"]';
    $this->assertXpath($xpath);

    // -- Block with wrapper.
    $xpath = '//div[@class = "tt-block-with-wrapper"]';
    $xpath .= '/div[@class = "block block-system block-system-branding-block"]';
    $xpath .= '/h2[text() = "Branding"]';
    $xpath .= '/following-sibling::a[img[contains(@src, "/core/themes/claro/logo.svg") and @alt="Home"]]';
    $xpath .= '/following-sibling::div[@class = "site-name"]/a';
    $this->assertXpath($xpath);

    // -- Region.
    $xpath = '//div[@class = "tt-region"]/div[@class = "region region-highlighted"]';
    $xpath .= '/div[contains(@class, "block-system-powered-by-block")]/span[. = "Powered by Drupal"]';
    $this->assertXpath($xpath);

    // -- Entity (default view mode).
    $xpath = '//div[@class = "tt-entity-default"]';
    $xpath .= '/article[contains(@class, "node") and contains(@class, "node--view-mode-full")]';
    $xpath .= '/div[@class = "node__content"]/div/p[text() = "The node content."]';
    $this->assertXpath($xpath);

    // -- Entity (teaser view mode).
    $xpath = '//div[@class = "tt-entity-teaser"]';
    $xpath .= '/article[contains(@class, "node") and contains(@class, "node--view-mode-teaser")]';
    $xpath .= '/h2/a/span[text() = "Alpha"]';
    $this->assertXpath($xpath);

    // -- Entity by UUID.
    $xpath = '//div[@class = "tt-entity-uuid"]';
    $xpath .= '/article[contains(@class, "node")]';
    $xpath .= '/div[@class = "node__content"]/div/p[text() = "The node content."]';
    $this->assertXpath($xpath);

    // -- Entity by UUID (missing).
    $xpath = '//div[@class = "tt-entity-uuid-missing" and . = ""]';
    $this->assertXpath($xpath);

    // -- Entity add form (unprivileged user).
    $xpath = '//div[@class = "tt-entity-add-form"]/form';
    $this->assertSession()->elementNotExists('xpath', $xpath);

    // -- Entity edit form (unprivileged user).
    $xpath = '//div[@class = "tt-entity-edit-form"]/form';
    $this->assertSession()->elementNotExists('xpath', $xpath);

    // Grant require permissions and test the forms again.
    $permissions = ['create page content', 'edit any page content'];
    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load(Role::ANONYMOUS_ID);
    $this->grantPermissions($role, $permissions);
    $this->drupalGet($this->getUrl());

    // -- Entity add form.
    $xpath = '//div[@class = "tt-entity-add-form"]/form';
    $xpath .= '//input[@name = "title[0][value]" and @value = ""]';
    $xpath .= '/../../../../..//div/input[@type = "submit" and @value = "Save"]';
    $this->assertXpath($xpath);

    // -- Entity edit form.
    $xpath = '//div[@class = "tt-entity-edit-form"]/form';
    $xpath .= '//input[@name = "title[0][value]" and @value = "Alpha"]';
    $xpath .= '/../../../../..//div/input[@type = "submit" and @value = "Save"]';
    $this->assertXpath($xpath);

    // -- Field.
    $xpath = '//div[@class = "tt-field"]/div[contains(@class, "field--name-body")]/p[text() != ""]';
    $this->assertXpath($xpath);

    // -- Menu.
    $xpath = '//div[@class = "tt-menu-default"]/ul[@class = "menu"]/li/a[text() = "Link 1"]/../ul[@class = "menu"]/li/ul[@class = "menu"]/li/a[text() = "Link 3"]';
    $this->assertXpath($xpath);

    // -- Menu with level option.
    $xpath = '//div[@class = "tt-menu-level"]/ul[@class = "menu"]/li/a[text() = "Link 2"]/../ul[@class = "menu"]/li/a[text() = "Link 3"]';
    $this->assertXpath($xpath);

    // -- Menu with depth option.
    $xpath = '//div[@class = "tt-menu-depth"]/ul[@class = "menu"]/li[not(ul)]/a[text() = "Link 1"]';
    $this->assertXpath($xpath);

    // -- Form.
    $xpath = '//div[@class = "tt-form"]/form[@class="system-cron-settings"]/input[@type = "submit" and @value = "Run cron"]';
    $this->assertXpath($xpath);

    // -- Image by FID.
    $xpath = '//div[@class = "tt-image-by-fid"]/img[contains(@src, "/files/image-test.png")]';
    $this->assertXpath($xpath);

    // -- Image by URI.
    $xpath = '//div[@class = "tt-image-by-uri"]/img[contains(@src, "/files/image-test.png")]';
    $this->assertXpath($xpath);

    // -- Image by UUID.
    $xpath = '//div[@class = "tt-image-by-uuid"]/img[contains(@src, "/files/image-test.png")]';
    $this->assertXpath($xpath);

    // -- Image with style.
    $xpath = '//div[@class = "tt-image-with-style"]/img[contains(@src, "/files/styles/thumbnail/public/image-test.png")]';
    $this->assertXpath($xpath);

    // -- Image with responsive style.
    $xpath = '//div[@class = "tt-image-with-responsive-style"]/picture/img[contains(@src, "/files/image-test.png")]';
    $this->assertXpath($xpath);

    // -- Token.
    $xpath = '//div[@class = "tt-token" and text() = "Drupal"]';
    $this->assertXpath($xpath);

    // -- Token with context.
    $xpath = '//div[@class = "tt-token-data" and text() = "Alpha"]';
    $this->assertXpath($xpath);

    // -- Config.
    $xpath = '//div[@class = "tt-config" and text() = "Anonymous"]';
    $this->assertXpath($xpath);

    // -- Page title.
    $xpath = '//div[@class = "tt-title" and text() = "Twig Tweak Test"]';
    $this->assertXpath($xpath);

    // -- URL.
    $url = Url::fromUserInput('/node/1', ['absolute' => TRUE])->toString();
    $xpath = sprintf('//div[@class = "tt-url"]/div[@data-case="default" and text() = "%s"]', $url);
    $this->assertXpath($xpath);

    // -- URL with langcode.
    $url = str_replace('node/1', 'ru/node/1', $url);
    $xpath = sprintf('//div[@class = "tt-url"]/div[@data-case="with-langcode" and text() = "%s"]', $url);
    $this->assertXpath($xpath);

    // -- External URL.
    $url = 'https://example.com/node?foo=bar&page=1#here';
    $xpath = sprintf('//div[@class = "tt-url"]/div[@data-case="external" and text() = "%s"]', $url);
    $this->assertXpath($xpath);

    // -- Link.
    $url = Url::fromUserInput('/node/1/edit', ['absolute' => TRUE]);
    $link = Link::fromTextAndUrl('Edit', $url)->toString();
    $xpath = '//div[@class = "tt-link"]';
    self::assertSame((string) $link, $this->xpath($xpath)[0]->getHtml());

    // -- Link with HTML.
    $text = Markup::create('<b>Edit</b>');
    $url = Url::fromUserInput('/node/1/edit', ['absolute' => TRUE]);
    $link = Link::fromTextAndUrl($text, $url)->toString();
    $xpath = '//div[@class = "tt-link-html"]';
    self::assertSame((string) $link, $this->xpath($xpath)[0]->getHtml());

    // -- Status messages.
    $xpath = '//div[@class = "tt-messages"]//div[contains(@class, "messages--status") and contains(., "Hello world!")]';
    $this->assertXpath($xpath);

    // -- Breadcrumb.
    $xpath = '//div[@class = "tt-breadcrumb"]/nav[@class = "breadcrumb"]/ol/li/a[text() = "Home"]';
    $this->assertXpath($xpath);

    // -- Protected link.
    $xpath = '//div[@class = "tt-link-access"]';
    self::assertSame('', $this->xpath($xpath)[0]->getHtml());

    // -- Token replacement.
    $xpath = '//div[@class = "tt-token-replace" and text() = "Site name: Drupal"]';
    $this->assertXpath($xpath);

    // -- Contextual links.
    $xpath = '//div[@class="tt-contextual-links" and not(div[@data-contextual-id])]';
    $this->assertXpath($xpath);

    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load(Role::ANONYMOUS_ID);
    $this->grantPermissions($role, ['access contextual links']);
    $this->drupalGet($this->getUrl());
    $xpath = '//div[@class="tt-contextual-links" and div[@data-contextual-id]]';
    $this->assertXpath($xpath);

    // -- Replace (preg).
    $xpath = '//div[@class = "tt-preg-replace" and text() = "FOO-bar"]';
    $this->assertXpath($xpath);

    // -- Image style.
    $xpath = '//div[@class = "tt-image-style" and contains(text(), "styles/thumbnail/public/images/ocean.jpg")]';
    $this->assertXpath($xpath);

    // -- Transliterate.
    $xpath = '//div[@class = "tt-transliterate" and contains(text(), "Privet!")]';
    $this->assertXpath($xpath);

    // -- Text format.
    $xpath = '//div[@class = "tt-check-markup"]';
    self::assertSame('<b>bold</b> strong', $this->xpath($xpath)[0]->getHtml());

    // -- Format size.
    $xpath = '//div[@class = "tt-format-size"]';
    self::assertSame('12.06 KB', $this->xpath($xpath)[0]->getHtml());

    // -- Truncate.
    $xpath = '//div[@class = "tt-truncate" and text() = "Hello…"]';
    $this->assertXpath($xpath);

    // -- 'with'.
    $xpath = '//div[@class = "tt-with"]/b[text() = "Example"]';
    $this->assertXpath($xpath);

    // -- Nested 'with'.
    $xpath = '//div[@class = "tt-with-nested" and text() = "{alpha:{beta:{gamma:456}}}"]';
    $this->assertXpath($xpath);

    // -- Data URI (SVG).
    $xpath = '//div[@class = "tt-data-uri-svg"]/img[@src = "data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iNTAiIGZpbGw9ImxpbWUiLz48L3N2Zz4="]';
    $this->assertXpath($xpath);

    // -- Data URI (Iframe).
    $xpath = '//div[@class = "tt-data-uri-iframe"]/iframe[@src = "data:text/html;charset=UTF-8;base64,PGgxPkhlbGxvIHdvcmxkITwvaDE+"]';
    $this->assertXpath($xpath);

    // -- 'children'.
    // cspell:disable-next-line
    $xpath = '//div[@class = "tt-children" and text() = "doremi"]';
    $this->assertXpath($xpath);

    // -- Entity view.
    $xpath = '//div[@class = "tt-node-view"]/article[contains(@class, "node--view-mode-default")]/h2[a/span[text() = "Alpha"]]';
    $xpath .= '/following-sibling::div[@class = "node__content"]/div/p';
    $this->assertXpath($xpath);

    // -- Field list view.
    $xpath = '//div[@class = "tt-field-list-view"]/span[contains(@class, "field--name-title") and text() = "Alpha"]';
    $this->assertXpath($xpath);

    // -- Field item view.
    $xpath = '//div[@class = "tt-field-item-view" and text() = "Alpha"]';
    $this->assertXpath($xpath);

    // -- File URI from image field.
    $xpath = '//div[@class = "tt-file-uri-from-image-field" and contains(text(), "public://image-test.png")]';
    $this->assertXpath($xpath);

    // -- File URI from a specific image field item.
    $xpath = '//div[@class = "tt-file-uri-from-image-field-delta" and contains(text(), "public://image-test.png")]';
    $this->assertXpath($xpath);

    // -- File URI from media field.
    $xpath = '//div[@class = "tt-file-uri-from-media-field" and contains(text(), "public://image-1.png")]';
    $this->assertXpath($xpath);

    // -- Image style from File URI from media field.
    $xpath = '//div[@class = "tt-image-style-from-file-uri-from-media-field" and contains(text(), "styles/thumbnail/public/image-1.png")]';
    $this->assertXpath($xpath);

    // -- File URL from URI (relative).
    $xpath = '//div[@class = "tt-file-url-from-uri" and contains(text(), "/files/image-test.png") and not(contains(text(), "http://"))]';
    $this->assertXpath($xpath);

    // -- File URL from URI (absolute).
    $xpath = '//div[@class = "tt-file-url-from-uri-absolute" and contains(text(), "/files/image-test.png") and contains(text(), "http://")]';
    $this->assertXpath($xpath);

    // -- File URL from image field.
    $xpath = '//div[@class = "tt-file-url-from-image-field" and contains(text(), "/files/image-test.png")]';
    $this->assertXpath($xpath);

    // -- File URL from a specific image field item.
    $xpath = '//div[@class = "tt-file-url-from-image-field-delta" and contains(text(), "/files/image-test.png")]';
    $this->assertXpath($xpath);

    // -- File URL from media field.
    $xpath = '//div[@class = "tt-file-url-from-media-field" and contains(text(), "/files/image-1.png")]';
    $this->assertXpath($xpath);

    // -- Entity URL (canonical).
    $xpath = '//div[@class = "tt-entity-url" and contains(text(), "/node/1#test") and not(contains(text(), "http"))]';
    $this->assertXpath($xpath);

    // -- Entity URL (absolute).
    $xpath = '//div[@class = "tt-entity-url-absolute" and contains(text(), "/node/1") and contains(text(), "http")]';
    $this->assertXpath($xpath);

    // -- Entity URL (edit form).
    $xpath = '//div[@class = "tt-entity-url-edit-form" and contains(text(), "/node/1/edit")]';
    $this->assertXpath($xpath);

    // -- Entity Link (canonical).
    $xpath = '//div[@class = "tt-entity-link"]/a[text() = "Alpha" and contains(@href, "/node/1")  and not(contains(@href, "http"))]';
    $this->assertXpath($xpath);

    // -- Entity Link (absolute).
    $xpath = '//div[@class = "tt-entity-link-absolute"]/a[text() = "Example" and contains(@href, "/node/1") and contains(@href, "http")]';
    $this->assertXpath($xpath);

    // -- Entity Link (edit form).
    $xpath = '//div[@class = "tt-entity-link-edit-form"]/a[text() = "Edit" and contains(@href, "/node/1/edit")]';
    $this->assertXpath($xpath);

    // -- Entity translation.
    // This is just a smoke test because the node is not translatable.
    $xpath = '//div[@class = "tt-translation" and contains(text(), "Alpha")]';
    $this->assertXpath($xpath);

    // -- Hook twig_tweak_functions_alter().
    $xpath = '//div[@class = "tt-functions_alter" and text() = "-=bar=-"]';
    $this->assertXpath($xpath);

    // -- Hook twig_tweak_filters_alter().
    $xpath = '//div[@class = "tt-filters_alter" and text() = "bar"]';
    $this->assertXpath($xpath);

    // -- Hook twig_tweak_tests_alter().
    $xpath = '//div[@class = "tt-tests_alter" and text() = "Yes"]';
    $this->assertXpath($xpath);
  }

  /**
   * Checks that an element specified by the xpath exists on the current page.
   */
  private function assertXpath(string $xpath): void {
    $this->assertSession()->elementExists('xpath', $xpath);
  }

}
