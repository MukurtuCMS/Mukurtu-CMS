<?php

namespace Drupal\Tests\search_api\Functional;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\ServerInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests our integration with the Configuration Read-only module.
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class ConfigReadOnlyIntegrationTest extends SearchApiBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api_test_db',
    'config_readonly',
  ];

  /**
   * The test server.
   */
  protected ServerInterface $server;

  /**
   * The test index.
   */
  protected IndexInterface $index;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Create an index and server to work with.
    $this->index = Index::load('database_search_index');
    $this->server = Server::load('database_search_server');

    // Add a configurable field to the index.
    $field = (new Field($this->index, 'url'))
      ->setType('text')
      ->setPropertyPath('search_api_url')
      ->setLabel('URI')
      ->setConfiguration(['absolute' => TRUE]);
    $this->index->addField($field)->save();

    // Log in, so we can test all the things.
    $this->drupalLogin($this->adminUser);

    // Turn on config readonly setting.
    $settings['settings']['config_readonly'] = (object) [
      'value' => TRUE,
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
  }

  /**
   * Visits various forms and ensures they are (not) editable, as appropriate.
   */
  public function testModuleForms(): void {
    $assert_session = $this->assertSession();
    $message = 'This form will not be saved because the configuration active store is read-only';
    $base_path = 'admin/config/search/search-api';
    $index_id = $this->index->id();
    $server_id = $this->server->id();

    // Warning should be shown on edit/disable/delete forms of both entity types
    // as well as the various other forms for modifying an index.
    $this->drupalGet("$base_path/index/$index_id/edit");
    $assert_session->pageTextContains($message);
    $this->drupalGet("$base_path/index/$index_id/disable");
    $assert_session->pageTextContains($message);
    $this->drupalGet("$base_path/index/$index_id/delete");
    $assert_session->pageTextContains($message);
    $this->drupalGet("$base_path/index/$index_id/fields");
    $assert_session->pageTextContains($message);
    $this->drupalGet("$base_path/index/$index_id/fields/add/nojs");
    $assert_session->pageTextContains($message);
    $this->drupalGet("$base_path/index/$index_id/fields/edit/url");
    $assert_session->pageTextContains($message);
    $this->drupalGet("$base_path/index/$index_id/processors");
    $assert_session->pageTextContains($message);

    $this->drupalGet("$base_path/server/$server_id/edit");
    $assert_session->pageTextContains($message);
    $this->drupalGet("$base_path/server/$server_id/disable");
    $assert_session->pageTextContains($message);
    $this->drupalGet("$base_path/server/$server_id/delete");
    $assert_session->pageTextContains($message);

    // The warning message should not be shown on forms that do not actually
    // modify their respective entities:
    // - the index "break lock" form;
    // - the confirm forms for clearing/reindexing or rebuilding the tracker.
    $this->drupalGet("$base_path/index/$index_id/fields/break-lock");
    $assert_session->pageTextNotContains($message);
    $this->drupalGet("$base_path/index/$index_id/reindex");
    $assert_session->pageTextNotContains($message);
    $this->drupalGet("$base_path/index/$index_id/clear");
    $assert_session->pageTextNotContains($message);
    $this->drupalGet("$base_path/index/$index_id/rebuild-tracker");
    $assert_session->pageTextNotContains($message);
    $this->drupalGet("$base_path/server/$server_id/clear");
    $assert_session->pageTextNotContains($message);
  }

}
