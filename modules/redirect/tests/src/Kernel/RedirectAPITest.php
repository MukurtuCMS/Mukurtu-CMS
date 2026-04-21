<?php

declare(strict_types=1);

namespace Drupal\Tests\redirect\Kernel;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\redirect\Entity\Redirect;
use Drupal\Core\Language\Language;
use Drupal\redirect\Exception\RedirectLoopException;
use Drupal\KernelTests\KernelTestBase;

/**
 * Redirect entity and redirect API test coverage.
 *
 * @group redirect
 */
class RedirectAPITest extends KernelTestBase {

  /**
   * The redirect storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['redirect', 'link', 'field', 'system', 'user', 'language', 'views', 'path_alias'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('redirect');
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installConfig(['redirect']);

    $language = ConfigurableLanguage::createFromLangcode('de');
    $language->save();

    $this->storage = $this->container->get('entity_type.manager')->getStorage('redirect');
  }

  /**
   * Test redirect entity logic.
   */
  public function testRedirectEntity() {
    // Create a redirect and test if hash has been generated correctly.
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $this->storage->create();
    // Test getters prior to any data being set.
    $this->assertSame(0, $redirect->getStatusCode());
    $this->assertIsInt($redirect->getCreated());
    $this->assertSame([], $redirect->getSource());
    $this->assertSame('', $redirect->getSourceUrl());
    $this->assertSame('/', $redirect->getSourcePathWithQuery());
    $this->assertSame([], $redirect->getRedirect());
    $this->assertNull($redirect->getRedirectUrl());
    $this->assertSame([], $redirect->getRedirectOptions());
    $this->assertNull($redirect->getRedirectOption('foo'));
    $this->assertNull($redirect->getHash());

    $redirect->setSource('some-url', ['key' => 'val']);
    $redirect->setRedirect('node');

    $redirect->save();
    $this->assertEquals(Redirect::generateHash('some-url', ['key' => 'val'], Language::LANGCODE_NOT_SPECIFIED), $redirect->getHash());
    // Update the redirect source query and check if hash has been updated as
    // expected.
    $redirect->setSource('some-url', ['key1' => 'val1']);
    $redirect->save();
    $this->assertEquals(Redirect::generateHash('some-url', ['key1' => 'val1'], Language::LANGCODE_NOT_SPECIFIED), $redirect->getHash());
    // Update the redirect source path and check if hash has been updated as
    // expected.
    $redirect->setSource('another-url', ['key1' => 'val1']);
    $redirect->save();
    $this->assertEquals(Redirect::generateHash('another-url', ['key1' => 'val1'], Language::LANGCODE_NOT_SPECIFIED), $redirect->getHash());
    // Update the redirect language and check if hash has been updated as
    // expected.
    $redirect->setLanguage('de');
    $redirect->save();
    $this->assertEquals(Redirect::generateHash('another-url', ['key1' => 'val1'], 'de'), $redirect->getHash());
    // Create a few more redirects to test the select.
    for ($i = 0; $i < 5; $i++) {
      /** @var \Drupal\redirect\Entity\Redirect $redirect */
      $redirect = $this->storage->create();
      $redirect->setSource($this->randomMachineName());
      $redirect->save();
    }
    /** @var \Drupal\redirect\RedirectRepository $repository */
    $repository = \Drupal::service('redirect.repository');
    $redirect = $repository->findMatchingRedirect('another-url', ['key1' => 'val1'], 'de');
    if (!empty($redirect)) {
      $this->assertEquals($redirect->getSourceUrl(), '/another-url?key1=val1');
    }
    else {
      $this->fail('Failed to find matching redirect.');
    }

    // Load the redirect based on url.
    $redirects = $repository->findBySourcePath('another-url');
    $redirect = array_shift($redirects);
    if (!empty($redirect)) {
      $this->assertEquals($redirect->getSourceUrl(), '/another-url?key1=val1');
    }
    else {
      $this->fail('Failed to find redirect by source path.');
    }

    // Test passthrough_querystring.
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $this->storage->create();
    $redirect->setSource('a-different-url');
    $redirect->setRedirect('node');
    $redirect->save();
    $redirect = $repository->findMatchingRedirect('a-different-url', ['key1' => 'val1'], 'de');
    if (!empty($redirect)) {
      $this->assertEquals($redirect->getSourceUrl(), '/a-different-url');
    }
    else {
      $this->fail('Failed to find redirect by source path with query string.');
    }

    // Add another redirect to the same path, with a query. This should always
    // be found before the source without a query set.
    /** @var \Drupal\redirect\Entity\Redirect $new_redirect */
    $new_redirect = $this->storage->create();
    $new_redirect->setSource('a-different-url', ['foo' => 'bar']);
    $new_redirect->setRedirect('node');
    $new_redirect->save();
    $found = $repository->findMatchingRedirect('a-different-url', ['foo' => 'bar'], 'de');
    if (!empty($found)) {
      $this->assertEquals($found->getSourceUrl(), '/a-different-url?foo=bar');
    }
    else {
      $this->fail('Failed to find a redirect by source path with query string.');
    }

    // Add a redirect to an external URL.
    /** @var \Drupal\redirect\Entity\Redirect $external_redirect */
    $external_redirect = $this->storage->create();
    $external_redirect->setSource('google');
    $external_redirect->setRedirect('https://google.com');
    $external_redirect->save();
    $found = $repository->findMatchingRedirect('google');
    if (!empty($found)) {
      $this->assertEquals($found->getRedirectUrl()->toString(), 'https://google.com');
    }
    else {
      $this->fail('Failed to find a redirect for google.');
    }

    // Hashes should be case-insensitive since the source paths are.
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $this->storage->create();
    $redirect->setSource('Case-Sensitive-Path');
    $redirect->setRedirect('node');
    $redirect->save();
    $found = $repository->findBySourcePath('case-sensitive-path');
    if (!empty($found)) {
      $found = reset($found);
      $this->assertEquals($found->getSourceUrl(), '/Case-Sensitive-Path');
    }
    else {
      $this->fail('findBySourcePath is case sensitive');
    }
    $found = $repository->findMatchingRedirect('case-sensitive-path');
    if (!empty($found)) {
      $this->assertEquals($found->getSourceUrl(), '/Case-Sensitive-Path');
    }
    else {
      $this->fail('findMatchingRedirect is case sensitive.');
    }
  }

  /**
   * Test disabled redirect entity logic.
   */
  public function testDisabledRedirectEntity(): void {
    /** @var \Drupal\redirect\RedirectRepository $repository */
    $repository = \Drupal::service('redirect.repository');

    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $this->storage->create();
    $redirect->setSource('test-disabled-source');
    $redirect->setRedirect('http://test-disabled-destination.com');
    $redirect->setUnpublished();
    $redirect->save();

    // Test RedirectRepository::findMatchingRedirect().
    $found_matching = $repository->findMatchingRedirect('test-disabled-source');
    $this->assertEmpty($found_matching);

    // Test RedirectRepository::findBySourcePath().
    $found_source = $repository->findBySourcePath('test-disabled-source');
    $this->assertEmpty($found_source);

    // Test RedirectRepository::findByDestinationUri().
    $found_destination = $repository->findByDestinationUri(['http://test-disabled-destination.com']);
    $this->assertEmpty($found_destination);

    // Enable the redirect and check that it can be found by all API methods.
    $redirect->setPublished();
    $redirect->save();

    $found_matching = $repository->findMatchingRedirect('test-disabled-source');
    $this->assertEquals('/test-disabled-source', $found_matching->getSourceUrl());

    $found_source = array_values($repository->findBySourcePath('test-disabled-source'));
    $this->assertEquals('/test-disabled-source', $found_source[0]->getSourceUrl());

    $found_destination = array_values($repository->findByDestinationUri(['http://test-disabled-destination.com']));
    $this->assertEquals('http://test-disabled-destination.com', $found_destination[0]->getRedirectUrl()->toString());
  }

  /**
   * Test slash is removed from source path in findMatchingRedirect.
   */
  public function testDuplicateRedirectEntry() {
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $this->storage->create();
    // The trailing slash should be removed on pre-save.
    $redirect->setSource('/foo/foo/', []);
    $redirect->setRedirect('foo');
    $redirect->save();

    $redirect_repository = \Drupal::service('redirect.repository');
    $matched_redirect = $redirect_repository->findMatchingRedirect('/foo/foo', [], 'en-AU');
    $this->assertNotNull($matched_redirect);

    $null_redirect = $redirect_repository->findMatchingRedirect('/foo/foo-bar', [], 'en-AU');
    $this->assertNull($null_redirect);
  }

  /**
   * Test redirect_sort_recursive().
   */
  public function testSortRecursive() {
    $test_cases = [
      [
        'input' => ['b' => 'aa', 'c' => ['c2' => 'aa', 'c1' => 'aa'], 'a' => 'aa'],
        'expected' => ['a' => 'aa', 'b' => 'aa', 'c' => ['c1' => 'aa', 'c2' => 'aa']],
        'callback' => 'ksort',
      ],
    ];
    foreach ($test_cases as $test_case) {
      $output = $test_case['input'];
      redirect_sort_recursive($output, $test_case['callback']);
      $this->assertSame($test_case['expected'], $output);
    }
  }

  /**
   * Test loop detection.
   */
  public function testLoopDetection() {
    // Add a chained redirect that isn't a loop.
    /** @var \Drupal\redirect\Entity\Redirect $one */
    $one = $this->storage->create();
    $one->setSource('my-path');
    $one->setRedirect('node');
    $one->save();
    /** @var \Drupal\redirect\Entity\Redirect $two */
    $two = $this->storage->create();
    $two->setSource('second-path');
    $two->setRedirect('my-path');
    $two->save();
    /** @var \Drupal\redirect\Entity\Redirect $three */
    $three = $this->storage->create();
    $three->setSource('third-path');
    $three->setRedirect('second-path');
    $three->save();

    /** @var \Drupal\redirect\RedirectRepository $repository */
    $repository = \Drupal::service('redirect.repository');
    $found = $repository->findMatchingRedirect('third-path');
    if (!empty($found)) {
      $this->assertEquals($found->getRedirectUrl()->toString(), '/node', 'Chained redirects properly resolved in findMatchingRedirect.');
    }
    else {
      $this->fail('Failed to resolve a chained redirect.');
    }

    // Create a loop.
    $one->setRedirect('third-path');
    $one->save();
    try {
      $repository->findMatchingRedirect('third-path');
      $this->fail('Failed to detect a redirect loop.');
    }
    catch (RedirectLoopException) {
    }
  }

  /**
   * Test loop detection reset.
   */
  public function testLoopDetectionReset() {
    // Add a chained redirect that isn't a loop.
    /** @var \Drupal\redirect\Entity\Redirect $source */
    $source = $this->storage->create();
    $source->setSource('source-redirect');
    $source->setRedirect('target');
    $source->save();

    /** @var \Drupal\redirect\Entity\Redirect $target */
    $target = $this->storage->create();
    $target->setSource('target');
    $target->setRedirect('second-target');
    $target->save();

    /** @var \Drupal\redirect\RedirectRepository $repository */
    $repository = \Drupal::service('redirect.repository');
    $found = $repository->findMatchingRedirect('target');
    $this->assertEquals($target->id(), $found->id());

    $found = $repository->findMatchingRedirect('source-redirect');
    $this->assertEquals($target->id(), $found->id());
  }

  /**
   * Test multilingual redirects.
   */
  public function testMultilingualCases() {
    // Add a redirect for english.
    /** @var \Drupal\redirect\Entity\Redirect $en_redirect */
    $en_redirect = $this->storage->create();
    $en_redirect->setSource('lang-path');
    $en_redirect->setRedirect('/about');
    $en_redirect->setLanguage('en');
    $en_redirect->save();

    // Add a redirect for germany.
    /** @var \Drupal\redirect\Entity\Redirect $en_redirect */
    $en_redirect = $this->storage->create();
    $en_redirect->setSource('lang-path');
    $en_redirect->setRedirect('node');
    $en_redirect->setLanguage('de');
    $en_redirect->save();

    // Check redirect for english.
    /** @var \Drupal\redirect\RedirectRepository $repository */
    $repository = \Drupal::service('redirect.repository');

    $found = $repository->findBySourcePath('lang-path');
    if (!empty($found)) {
      $this->assertEquals($found[1]->getRedirectUrl()->toString(), '/about', 'Multilingual redirect resolved properly.');
      $this->assertEquals($found[1]->get('language')->value, 'en', 'Multilingual redirect resolved properly.');
    }
    else {
      $this->fail('Failed to resolve the multilingual redirect.');
    }

    // Check redirect for germany.
    \Drupal::configFactory()->getEditable('system.site')->set('default_langcode', 'de')->save();
    /** @var \Drupal\redirect\RedirectRepository $repository */
    $repository = \Drupal::service('redirect.repository');
    $found = $repository->findBySourcePath('lang-path');
    if (!empty($found)) {
      $this->assertEquals($found[2]->getRedirectUrl()->toString(), '/node', 'Multilingual redirect resolved properly.');
      $this->assertEquals($found[2]->get('language')->value, 'de', 'Multilingual redirect resolved properly.');
    }
    else {
      $this->fail('Failed to resolve the multilingual redirect.');
    }
  }

  /**
   * Tests \Drupal\redirect\Plugin\Validation\Constraint\UniqueHashValidator.
   */
  public function testDuplicateRedirectHashValidation() {
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $this->storage->create();
    $redirect->setSource('lang-path');
    $redirect->setRedirect('/about');
    $this->assertCount(0, $redirect->validate());
    $redirect->save();

    /** @var \Drupal\redirect\Entity\Redirect $redirect2 */
    $redirect2 = $this->storage->create();
    $redirect2->setSource('lang-path');
    $redirect2->setRedirect('/about');
    $violations = $redirect2->validate();
    $this->assertCount(1, $violations);
    $this->assertSame('The source path <em class="placeholder">lang-path</em> is already being redirected. Do you want to <a href="/admin/config/search/redirect/edit/1">edit the existing redirect</a>?', (string) $violations[0]->getMessage());

    $redirect2->setSource('lang-path?foo=bar');
    $this->assertCount(0, $redirect2->validate());
    $redirect2->save();

    /** @var \Drupal\redirect\Entity\Redirect $redirect3 */
    $redirect3 = $this->storage->create();
    $redirect3->setSource('lang-path?foo=bar');
    $redirect3->setRedirect('/about');
    $violations = $redirect3->validate();
    $this->assertCount(1, $violations);
    $this->assertSame('The source path <em class="placeholder">lang-path?foo=bar</em> is already being redirected. Do you want to <a href="/admin/config/search/redirect/edit/2">edit the existing redirect</a>?', (string) $violations[0]->getMessage());

    $redirect3->setLanguage('en');
    $this->assertCount(0, $redirect3->validate());
    $redirect3->save();

    /** @var \Drupal\redirect\Entity\Redirect $redirect4 */
    $redirect4 = $this->storage->create();
    $redirect4->setSource('lang-path?foo=bar');
    $redirect4->setRedirect('/about');
    $redirect4->setLanguage('en');
    $violations = $redirect4->validate();
    $this->assertCount(1, $violations);
    $this->assertSame('The source path <em class="placeholder">lang-path?foo=bar</em> is already being redirected. Do you want to <a href="/admin/config/search/redirect/edit/3">edit the existing redirect</a>?', (string) $violations[0]->getMessage());
  }

}
