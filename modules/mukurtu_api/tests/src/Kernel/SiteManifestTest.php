<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_api\Kernel;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\mukurtu_core\MukurtuVersion;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the public site manifest an app reads before sign-in (#400).
 */
#[Group('mukurtu_api')]
class SiteManifestTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'geofield',
    'image',
    'language',
    'leaflet',
    'mukurtu_api',
    'mukurtu_core',
    'mukurtu_design',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'language']);
    $this->config('system.site')
      ->set('name', 'Example Archive')
      ->set('slogan', 'Shared with care')
      ->save();
    $this->config('mukurtu_design.settings')
      ->set('palette', 'red-bone')
      ->set('colors', ['brand_primary' => '#ac143a'])
      ->save();
    ConfigurableLanguage::createFromLangcode('es')->save();
    // Pick the interface language from the URL prefix, as real sites do.
    $this->config('language.types')
      ->set('negotiation.language_interface.enabled', ['language-url' => -8])
      ->save();
    $this->config('language.negotiation')
      ->set('url.prefixes', ['en' => '', 'es' => 'es'])
      ->save();
    // The language path processor is only registered once the site is
    // multilingual, so rebuild the container now that it is.
    $this->container->get('kernel')->rebuildContainer();
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Sends an anonymous GET request and returns the response.
   */
  protected function get(string $path): Response {
    $request = Request::create($path);
    return $this->container->get('http_kernel')->handle($request);
  }

  /**
   * Anonymous visitors get the manifest with every documented key.
   */
  public function testAnonymousGetsManifest(): void {
    $response = $this->get('/api/mukurtu/manifest');
    $this->assertSame(200, $response->getStatusCode());
    $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));

    $data = json_decode((string) $response->getContent(), TRUE);
    $this->assertSame(
      ['api', 'mukurtu_version', 'name', 'slogan', 'logo', 'palette', 'colors', 'languages', 'capabilities'],
      array_keys($data),
    );
    $this->assertSame(0, $data['api']['version']);
    $this->assertSame(MukurtuVersion::get(), $data['mukurtu_version']);
    $this->assertNotSame('', $data['mukurtu_version'], 'VERSION.md is readable from the profile.');
    $this->assertSame('Example Archive', $data['name']);
    $this->assertSame('Shared with care', $data['slogan']);
    $this->assertSame('red-bone', $data['palette']);
    $this->assertSame('#ac143a', $data['colors']['brand_primary']);
    $this->assertFalse($data['capabilities']['jsonapi']);

    $languages = array_column($data['languages'], NULL, 'id');
    $this->assertArrayHasKey('en', $languages);
    $this->assertArrayHasKey('es', $languages);
    $this->assertTrue($languages['en']['default']);
    $this->assertFalse($languages['es']['default']);
    $this->assertSame('ltr', $languages['es']['direction']);
  }

  /**
   * The site name follows the request language, through config translation.
   */
  public function testSiteNameIsTranslated(): void {
    $this->container->get('language_manager')
      ->getLanguageConfigOverride('es', 'system.site')
      ->set('name', 'Archivo de ejemplo')
      ->save();

    $data = json_decode((string) $this->get('/es/api/mukurtu/manifest')->getContent(), TRUE);
    $this->assertSame('Archivo de ejemplo', $data['name']);

    $data = json_decode((string) $this->get('/api/mukurtu/manifest')->getContent(), TRUE);
    $this->assertSame('Example Archive', $data['name']);
  }

  /**
   * The response is cacheable and is invalidated when its sources change.
   */
  public function testCacheability(): void {
    $response = $this->get('/api/mukurtu/manifest');
    $this->assertInstanceOf(CacheableResponseInterface::class, $response);
    $metadata = $response->getCacheableMetadata();
    foreach ([
      'config:system.site',
      'config:mukurtu_design.settings',
      'config:system.theme',
      'config:configurable_language_list',
    ] as $tag) {
      $this->assertContains($tag, $metadata->getCacheTags());
    }
    $this->assertContains('languages:language_interface', $metadata->getCacheContexts());
  }

  /**
   * Nothing in the manifest points at private files.
   */
  public function testNoPrivateFiles(): void {
    $content = (string) $this->get('/api/mukurtu/manifest')->getContent();
    $this->assertStringNotContainsString('private://', $content);
    $this->assertStringNotContainsString('/system/files/', $content);
  }

}
