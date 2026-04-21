<?php

declare(strict_types=1);

namespace Drupal\Tests\redirect\Functional;

use Drupal\Core\Language\Language;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;

/**
 * UI tests for redirect module.
 *
 * @group redirect
 */
class RedirectUITest extends BrowserTestBase {

  use AssertRedirectTrait;

  /**
   * The admin user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * The redirect repository.
   *
   * @var \Drupal\redirect\RedirectRepository
   */
  protected $repository;

  /**
   * The Sql content entity storage.
   *
   * @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage
   */
  protected $storage;

  /**
   * The maximum redirects.
   *
   * @var int
   */
  public $maximumRedirects;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'redirect',
    'node',
    'path',
    'dblog',
    'views',
    'taxonomy',
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

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->adminUser = $this->drupalCreateUser([
      'administer redirects',
      'administer redirect settings',
      'access content',
      'bypass node access',
      'create url aliases',
      'administer taxonomy',
      'administer url aliases',
    ]);

    $this->repository = \Drupal::service('redirect.repository');

    $this->storage = \Drupal::entityTypeManager()->getStorage('redirect');
  }

  /**
   * Tests redirects being automatically created upon path alias change.
   */
  public function testAutomaticRedirects() {
    $this->drupalLogin($this->adminUser);

    // Create a node and update its path alias which should result in a redirect
    // being automatically created from the old alias to the new one.
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'langcode' => Language::LANGCODE_NOT_SPECIFIED,
      'path' => ['alias' => '/node_test_alias'],
    ]);
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->pageTextContains('No URL redirects available.');
    $this->submitForm(['path[0][alias]' => '/node_test_alias_updated'], 'Save');

    $redirect = $this->repository->findMatchingRedirect('node_test_alias', [], Language::LANGCODE_NOT_SPECIFIED);
    $this->assertEquals(Url::fromUri('base:node_test_alias_updated')->toString(), $redirect->getRedirectUrl()->toString());
    // Test if the automatically created redirect works.
    $this->assertRedirect('node_test_alias', 'node_test_alias_updated');

    // Test that changing the path back deletes the first redirect, creates
    // a new one and does not result in a loop.
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->submitForm(['path[0][alias]' => '/node_test_alias'], 'Save');
    $redirect = $this->repository->findMatchingRedirect('node_test_alias', [], Language::LANGCODE_NOT_SPECIFIED);
    $this->assertTrue(empty($redirect));

    \Drupal::service('path_alias.manager')->cacheClear();
    $redirect = $this->repository->findMatchingRedirect('node_test_alias_updated', [], Language::LANGCODE_NOT_SPECIFIED);

    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->pageTextContains($redirect->getSourcePathWithQuery());
    $this->assertSession()->linkByHrefExists(Url::fromRoute('entity.redirect.edit_form', ['redirect' => $redirect->id()])->toString());
    $this->assertSession()->linkByHrefExists(Url::fromRoute('entity.redirect.delete_form', ['redirect' => $redirect->id()])->toString());

    $this->assertEquals(Url::fromUri('base:node_test_alias')->toString(), $redirect->getRedirectUrl()->toString());
    // Test if the automatically created redirect works.
    $this->assertRedirect('node_test_alias_updated', 'node_test_alias');

    // Test that the redirect will be deleted upon node deletion.
    $this->drupalGet('node/' . $node->id() . '/delete');
    $this->submitForm([], 'Delete');
    $redirect = $this->repository->findMatchingRedirect('node_test_alias_updated', [], Language::LANGCODE_NOT_SPECIFIED);
    $this->assertTrue(empty($redirect));

    // Create a term and update its path alias and check if we have a redirect
    // from the previous path alias to the new one.
    $term = $this->createTerm($this->createVocabulary());
    $this->drupalGet('taxonomy/term/' . $term->id() . '/edit');
    $this->submitForm(['path[0][alias]' => '/term_test_alias_updated'], 'Save');
    $redirect = $this->repository->findMatchingRedirect('term_test_alias');
    $this->assertEquals(Url::fromUri('base:term_test_alias_updated')->toString(), $redirect->getRedirectUrl()->toString());
    // Test if the automatically created redirect works.
    $this->assertRedirect('term_test_alias', 'term_test_alias_updated');

    // Test the path alias update via the admin path form.
    $this->drupalGet('admin/config/search/path/add');
    $this->submitForm([
      'path[0][value]' => '/node',
      'alias[0][value]' => '/aaa_path_alias',
    ], 'Save');
    // Note that here we rely on fact that we land on the path alias list page
    // and the default sort is by the alias, which implies that the first edit
    // link leads to the edit page of the aaa_path_alias.
    $this->clickLink('Edit');
    $this->submitForm(['alias[0][value]' => '/aaa_path_alias_updated'], 'Save');
    $redirect = $this->repository->findMatchingRedirect('aaa_path_alias', [], 'en');
    $this->assertEquals(Url::fromUri('base:aaa_path_alias_updated')->toString(), $redirect->getRedirectUrl()->toString());
    // Test if the automatically created redirect works.
    $this->assertRedirect('aaa_path_alias', 'aaa_path_alias_updated');

    // Test the automatically created redirect shows up in the form correctly.
    $this->drupalGet('admin/config/search/redirect/edit/' . $redirect->id());
    $this->assertSession()->fieldValueEquals('redirect_source[0][path]', 'aaa_path_alias');
    $this->assertSession()->fieldValueEquals('redirect_redirect[0][uri]', '/node');
  }

  /**
   * Tests redirect form with invalid URLs.
   *
   * This test ensures that redirect URLS entered via query parameters or the
   * form element work the same way.
   */
  public function testInvalidUrls() {
    $this->drupalLogin($this->adminUser);

    $invalid_urls = [
      // Not a valid URL.
      'http://ex!ample.com',
      // No route for this internal URL exists.
      '/does_not_exist',
    ];
    foreach ($invalid_urls as $i => $invalid_url) {
      $this->drupalGet('admin/config/search/redirect/add', [
        'query' => [
          'redirect' => $invalid_url,
        ],
      ]);
      $this->submitForm([
        'redirect_source[0][path]' => 'bar' . $i,
      ], 'Save');
      $this->assertSession()->pageTextContains('The redirect has been saved.');

      $this->drupalGet('admin/config/search/redirect/add');
      $this->submitForm([
        'redirect_source[0][path]' => 'foo' . $i,
        'redirect_redirect[0][uri]' => $invalid_url,
      ], 'Save');
      $this->assertSession()->pageTextContains('The redirect has been saved.');
    }
  }

  /**
   * Test the redirect loop protection and logging.
   */
  public function testRedirectLoop() {
    // Redirect loop redirection only works when page caching is disabled.
    \Drupal::service('module_installer')->uninstall(['page_cache']);

    /** @var \Drupal\redirect\Entity\Redirect $redirect1 */
    $redirect1 = $this->storage->create();
    $redirect1->setSource('node');
    $redirect1->setRedirect('admin');
    $redirect1->setStatusCode(301);
    $redirect1->save();

    /** @var \Drupal\redirect\Entity\Redirect $redirect2 */
    $redirect2 = $this->storage->create();
    $redirect2->setSource('admin');
    $redirect2->setRedirect('node');
    $redirect2->setStatusCode(301);
    $redirect2->save();

    $this->maximumRedirects = 10;
    $this->drupalGet('node');
    $this->assertSession()->pageTextContains('Service unavailable');
    $this->assertSession()->statusCodeEquals(503);

    $log = \Drupal::database()->select('watchdog')->fields('watchdog')->condition('type', 'redirect')->execute()->fetchAll();
    if (count($log) == 0) {
      $this->fail('Redirect loop has not been logged');
    }
    else {
      $log = reset($log);
      $this->assertEquals(RfcLogLevel::WARNING, $log->severity);
      $this->assertEquals('Redirect loop identified at %path for redirect %rid', $log->message);
      $this->assertEquals(['%path' => '/node', '%rid' => $redirect1->id()], unserialize($log->variables));
    }
  }

  /**
   * Returns a new vocabulary with random properties.
   */
  public function createVocabulary() {
    // Create a vocabulary.
    $vocabulary = Vocabulary::create([
      'name' => $this->randomMachineName(),
      'description' => $this->randomMachineName(),
      'vid' => mb_strtolower($this->randomMachineName()),
      'langcode' => Language::LANGCODE_NOT_SPECIFIED,
      'weight' => mt_rand(0, 10),
    ]);
    $vocabulary->save();
    return $vocabulary;
  }

  /**
   * Returns a new term with random properties in vocabulary $vid.
   */
  public function createTerm($vocabulary) {
    $filter_formats = filter_formats();
    $format = array_pop($filter_formats);
    $term = Term::create([
      'name' => $this->randomMachineName(),
      'description' => [
        'value' => $this->randomMachineName(),
        // Use the first available text format.
        'format' => $format->id(),
      ],
      'vid' => $vocabulary->id(),
      'langcode' => Language::LANGCODE_NOT_SPECIFIED,
      'path' => ['alias' => '/term_test_alias'],
    ]);
    $term->save();
    return $term;
  }

  /**
   * Test external destinations.
   */
  public function testExternal() {
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $this->storage->create();
    $redirect->setSource('a-path');
    // @todo Redirect::setRedirect() assumes that all redirects are internal.
    $redirect->redirect_redirect->set(0, ['uri' => 'https://www.example.org']);
    $redirect->setStatusCode(301);
    $redirect->save();
    $this->assertRedirect('a-path', 'https://www.example.org');
    $this->drupalLogin($this->adminUser);
  }

}
