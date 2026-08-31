<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_search\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_search_update_40006().
 *
 * The "Search API Solr" option was removed from the Search Backend settings
 * form because Solr support isn't ready on any site. This update hook resets
 * any existing config left pointed at the now-unavailable "solr" backend so
 * no site is left referencing an option the UI no longer offers.
 *
 * mukurtu_search itself is not enabled here: its declared dependency chain
 * (mukurtu_collection, paragraphs, media, search_api_glossary, token) isn't
 * needed to exercise mukurtu_search_update_40006() directly, mirroring
 * StaticTaxonomyNameFieldRestoreTest.
 *
 * @see mukurtu_search_update_40006()
 */
#[Group('mukurtu_search')]
class SearchBackendUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_search');
    require_once $module_path . '/mukurtu_search.install';
  }

  /**
   * Tests the update hook resets a "solr" backend value to "db".
   */
  public function testUpdateResetsSolrBackendToDb(): void {
    \Drupal::configFactory()->getEditable('mukurtu_search.settings')
      ->set('backend', 'solr')
      ->save();

    mukurtu_search_update_40006();

    $backend = \Drupal::config('mukurtu_search.settings')->get('backend');
    $this->assertSame('db', $backend);
  }

  /**
   * Tests the update hook leaves an already-"db" backend untouched.
   */
  public function testUpdateLeavesDbBackendUntouched(): void {
    \Drupal::configFactory()->getEditable('mukurtu_search.settings')
      ->set('backend', 'db')
      ->save();

    mukurtu_search_update_40006();

    $backend = \Drupal::config('mukurtu_search.settings')->get('backend');
    $this->assertSame('db', $backend);
  }

  /**
   * Tests the update hook is a no-op when the backend key was never set.
   */
  public function testUpdateLeavesUnsetBackendUnset(): void {
    mukurtu_search_update_40006();

    $backend = \Drupal::config('mukurtu_search.settings')->get('backend');
    $this->assertNull($backend);
  }

}
