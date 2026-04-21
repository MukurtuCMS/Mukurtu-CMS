<?php

declare(strict_types=1);

namespace Drupal\Tests\redirect\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Cache tests for redirect module.
 *
 * @group redirect
 */
class RedirectCacheTest extends BrowserTestBase {

  use AssertRedirectTrait;

  /**
   * The Sql content entity storage.
   *
   * @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage
   */
  protected $storage;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'redirect',
    'test_page_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->storage = \Drupal::entityTypeManager()->getStorage('redirect');
  }

  /**
   * Test cache tags.
   */
  public function testCacheTags() {
    /** @var \Drupal\redirect\Entity\Redirect $redirect1 */
    $redirect1 = $this->storage->create();
    $redirect1->setSource('test-redirect');
    $redirect1->setRedirect('test-page');
    $redirect1->setStatusCode(301);
    $redirect1->save();

    $response = $this->assertRedirect('test-redirect', 'test-page');
    // Note, self::assertCacheTag() cannot be used here since it only looks at
    // the final set of headers.
    $expected = 'http_response ' . implode(' ', $redirect1->getCacheTags());
    $this->assertEquals($expected, $response->getHeader('x-drupal-cache-tags')[0], 'Redirect cache tags properly set.');

    // First request should be a cache MISS.
    $this->assertEquals('MISS', $response->getHeader('x-drupal-cache')[0], 'First request to the redirect was not cached.');

    // Second request should be cached.
    $response = $this->assertRedirect('test-redirect', 'test-page');
    $this->assertEquals('HIT', $response->getHeader('x-drupal-cache')[0], 'The second request to the redirect was cached.');

    /** @var \Drupal\redirect\Entity\Redirect $redirect2 */
    $redirect2 = $this->storage->create();
    $redirect2->setSource('test-redirect2');
    $redirect2->setRedirect('test-redirect');
    $redirect2->setStatusCode(301);
    $redirect2->save();

    $response = $this->assertRedirect('test-redirect2', 'test-page');
    // Note, self::assertCacheTag() cannot be used here since it only looks at
    // the final set of headers.
    $expected = 'http_response ' . implode(' ', array_merge($redirect1->getCacheTags(), $redirect2->getCacheTags()));
    $this->assertEquals($expected, $response->getHeader('x-drupal-cache-tags')[0], 'Redirect cache tags properly set.');

    // First request should be a cache MISS.
    $this->assertEquals('MISS', $response->getHeader('x-drupal-cache')[0], 'First request to the redirect was not cached.');

    // Second request should be cached.
    $response = $this->assertRedirect('test-redirect2', 'test-page');
    $this->assertEquals('HIT', $response->getHeader('x-drupal-cache')[0], 'The second request to the redirect was cached.');

    $this->drupalLogin($this->drupalCreateUser([
      'administer redirects',
      'administer redirect settings',
    ]));
    $this->drupalGet('admin/config/search/redirect/edit/' . $redirect1->id());
    $this->submitForm(['redirect_redirect[0][uri]' => '/test-render-title'], 'Save');
    $this->drupalLogout();

    $response = $this->assertRedirect('test-redirect', 'test-render-title');
    $this->assertEquals('MISS', $response->getHeader('x-drupal-cache')[0], 'First request to the redirect was not cached.');
    $response = $this->assertRedirect('test-redirect2', 'test-render-title');
    $this->assertEquals('MISS', $response->getHeader('x-drupal-cache')[0], 'First request to the redirect was not cached.');

    $this->drupalLogin($this->drupalCreateUser([
      'administer redirects',
      'administer redirect settings',
    ]));
    $this->drupalGet('admin/config/search/redirect/edit/' . $redirect2->id());
    $this->submitForm(['redirect_redirect[0][uri]' => '/test-page'], 'Save');
    $this->drupalLogout();

    $response = $this->assertRedirect('test-redirect', 'test-render-title');
    $this->assertEquals('HIT', $response->getHeader('x-drupal-cache')[0], 'First request to the redirect was not cached.');
    $response = $this->assertRedirect('test-redirect2', 'test-page');
    $this->assertEquals('MISS', $response->getHeader('x-drupal-cache')[0], 'First request to the redirect was not cached.');

    // Ensure that the redirect has been cleared from cache when deleted.
    $redirect1->delete();
    $this->drupalGet('test-redirect');
    $this->assertSession()->statusCodeEquals(404);
  }

}
