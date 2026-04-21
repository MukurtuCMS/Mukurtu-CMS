<?php

namespace Drupal\Tests\search_api_solr\Kernel;

use Drupal\Core\Language\LanguageInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\Utility\Utility;
use Drupal\search_api_solr\Controller\SolrConfigSetController;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\Utility\SolrCommitTrait;
use Drupal\search_api_solr\Utility\Utility as SolrUtility;
use Drupal\Tests\search_api_solr\Traits\InvokeMethodTrait;
use Drupal\user\Entity\User;

/**
 * Tests index and search capabilities using the Solr search backend.
 *
 * @group search_api_solr
 */
class SearchApiSolrTest extends SolrBackendTestBase {

  use SolrCommitTrait;
  use InvokeMethodTrait;

  /**
   * The language IDs.
   *
   * @var array
   */
  protected $languageIds = [
    'de' => 'de',
    'de-at' => 'de',
    'en' => 'en',
    'nl' => 'nl',
  ];

  /**
   * More language IDs.
   *
   * Keeping all languages installed will lead to massive multilingual
   * queries in search_api's tests. Therefore we split the language list
   * into languages that should be available in all test and those only
   * required for special tests.
   *
   * @var array
   *
   * @see checkSchemaLanguages()
   */
  protected $moreLanguageIds = [
    'ar' => 'ar',
    'bg' => 'bg',
    'ca' => 'ca',
    'cs' => 'cs',
    'cy' => 'cy',
    'da' => 'da',
    'el' => 'el',
    'es' => 'es',
    'et' => 'et',
    'fa' => 'fa',
    'fi' => 'fi',
    'fr' => 'fr',
    'ga' => 'ga',
    'hi' => 'hi',
    'hr' => 'hr',
    'hu' => 'hu',
    'id' => 'id',
    'it' => 'it',
    'ja' => 'ja',
    'ko' => 'ko',
    'lv' => 'lv',
    'nb' => 'nb',
    'nn' => 'nn',
    'pl' => 'pl',
    'pt-br' => 'pt_br',
    'pt-pt' => 'pt_pt',
    'ro' => 'ro',
    'ru' => 'ru',
    'sk' => 'sk',
    'sr' => 'sr',
    'sv' => 'sv',
    'th' => 'th',
    'tr' => 'tr',
    'xx' => FALSE,
    'uk' => 'uk',
    'zh-hans' => 'zh_hans',
    'zh-hant' => 'zh_hant',
  ];

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  protected static $modules = [
    'language',
    'search_api_solr_legacy',
    'user',
  ];

  /**
   * The fields helper.
   *
   * @var \Drupal\search_api\Utility\FieldsHelperInterface
   */
  protected $fieldsHelper;

  /**
   * {@inheritdoc}
   */
  protected function installConfigs() {
    foreach (array_keys($this->languageIds) as $language_id) {
      ConfigurableLanguage::createFromLangcode($language_id)->save();
    }

    parent::installConfigs();
  }

  /**
   * {@inheritdoc}
   */
  protected function commonSolrBackendSetUp() {
    parent::commonSolrBackendSetUp();

    $this->installEntitySchema('user');
    $this->fieldsHelper = \Drupal::getContainer()->get('search_api.fields_helper');
  }

  /**
   * {@inheritdoc}
   */
  protected function backendSpecificRegressionTests() {
    $this->regressionTest2888629();
    $this->indexPrefixTest();
  }

  /**
   * Tests index prefix.
   */
  protected function indexPrefixTest() {
    $backend = Server::load($this->serverId)->getBackend();
    $index = $this->getIndex();
    $prefixed_index_id = $this->invokeMethod($backend, 'getIndexId', [$index]);
    $this->assertEquals('server_prefixindex_prefix' . $index->id(), $prefixed_index_id);
  }

  /**
   * Regression tests for facets with counts of 0.
   *
   * @see https://www.drupal.org/node/1658964
   */
  protected function regressionTest1658964() {
    return;

    // @todo activate this regression test.
    // @codingStandardsIgnoreStart
    $query = $this->buildSearch();
    $facets['type'] = [
      'field' => 'type',
      'limit' => 0,
      'min_count' => 0,
      'missing' => TRUE,
    ];
    $query->setOption('search_api_facets', $facets);
    $query->addCondition('type', 'article');
    $query->range(0, 0);
    $results = $query->execute();
    $expected = [
      ['count' => 2, 'filter' => '"article"'],
      ['count' => 0, 'filter' => '!'],
      ['count' => 0, 'filter' => '"item"'],
    ];
    $facets = $results->getExtraData('search_api_facets', [])['type'];
    usort($facets, [$this, 'facetCompare']);
    $this->assertEquals($expected, $facets, 'Correct facets were returned');
    // @codingStandardsIgnoreEnd
  }

  /**
   * Regression tests for #2469547.
   */
  protected function regressionTest2469547() {
    return;

    // @todo activate this regression test.
    // @codingStandardsIgnoreStart
    $query = $this->buildSearch();
    $facets = [];
    $facets['body'] = [
      'field' => 'body',
      'limit' => 0,
      'min_count' => 1,
      'missing' => FALSE,
    ];
    $query->setOption('search_api_facets', $facets);
    $query->addCondition('id', 5, '<>');
    $query->range(0, 0);
    $results = $query->execute();
    $expected = $this->getExpectedFacetsOfRegressionTest2469547();
    // We can't guarantee the order of returned facets, since "bar" and "foobar"
    // both occur once, so we have to manually sort the returned facets first.
    $facets = $results->getExtraData('search_api_facets', [])['body'];
    usort($facets, [$this, 'facetCompare']);
    $this->assertEquals($expected, $facets, 'Correct facets were returned for a fulltext field.');
    // @codingStandardsIgnoreEnd
  }

  /**
   * Regression tests for #2888629.
   */
  protected function regressionTest2888629() {
    $query = $this->buildSearch();
    $query->addCondition('category', NULL);
    $results = $query->execute();
    $this->assertResults([3], $results, 'comparing against NULL');

    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR');
    $conditions->addCondition('category', 'article_category', '<>');
    $conditions->addCondition('category', NULL);
    $query->addConditionGroup($conditions);
    $results = $query->execute();
    $this->assertResults([1, 2, 3], $results, 'group comparing against category NOT article_category OR category NULL');

    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('AND');
    $conditions->addCondition('body', NULL, '<>');
    $conditions->addCondition('category', 'article_category', '<>');
    $conditions->addCondition('category', NULL, '<>');
    $query->addConditionGroup($conditions);
    $results = $query->execute();
    $this->assertResults([1, 2], $results, 'group comparing against body NOT NULL AND category NOT article_category AND category NOT NULL');
  }

  /**
   * {@inheritdoc}
   */
  public function searchSuccess() {
    parent::searchSuccess();

    $parse_mode_manager = \Drupal::service('plugin.manager.search_api.parse_mode');
    $parse_mode_direct = $parse_mode_manager->createInstance('direct');

    $results = $this->buildSearch('+test +case', [], ['body'])
      ->setParseMode($parse_mode_direct)
      ->execute();
    $this->assertResults([1, 2, 3], $results, 'Parse mode direct with AND');

    $results = $this->buildSearch('test -case', [], ['body'])
      ->setParseMode($parse_mode_direct)
      ->execute();
    $this->assertResults([4], $results, 'Parse mode direct with NOT');

    $results = $this->buildSearch('"test case"', [], ['body'])
      ->setParseMode($parse_mode_direct)
      ->execute();
    $this->assertResults([1, 2], $results, 'Parse mode direct with phrase');
  }

  /**
   * Return the expected facets for regression test 2469547.
   *
   * The facets differ for Solr backends because of case-insensitive filters.
   *
   * @return array
   *   An array of facet results.
   */
  protected function getExpectedFacetsOfRegressionTest2469547() {
    return [
      ['count' => 4, 'filter' => '"test"'],
      ['count' => 3, 'filter' => '"case"'],
      ['count' => 1, 'filter' => '"bar"'],
      ['count' => 1, 'filter' => '"foobar"'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function checkModuleUninstall() {
    // See whether clearing the server works.
    // Regression test for #2156151.
    /** @var \Drupal\search_api\ServerInterface $server */
    $server = Server::load($this->serverId);
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = Index::load($this->indexId);
    $server->deleteAllIndexItems($index);
    $this->ensureCommit($index);
    $query = $this->buildSearch();
    $results = $query->execute();
    $this->assertEquals(0, $results->getResultCount(), 'Clearing the server worked correctly.');
  }

  /**
   * {@inheritdoc}
   */
  protected function assertIgnored(ResultSetInterface $results, array $ignored = [], $message = 'No keys were ignored.') {
    // Nothing to do here since the Solr backend doesn't keep a list of ignored
    // fields.
  }

  /**
   * Checks backend specific features.
   */
  protected function checkBackendSpecificFeatures() {
    $this->checkSchemaLanguages();
    $this->checkBasicAuth();
    $this->checkQueryParsers();
    $this->checkQueryConditions();
    $this->checkHighlight();
    $this->checkSearchResultGrouping();
    $this->clearIndex();
    $this->checkDatasourceAdditionAndDeletion();
    $this->clearIndex();
    $this->checkRetrieveData();
    $this->clearIndex();
    $this->checkIndexFallback();
    $this->clearIndex();
    $this->checkSearchResultSorts();
  }

  /**
   * Tests if all supported languages are deployed correctly.
   */
  protected function checkSchemaLanguages() {
    $languages = [];
    foreach (array_keys($this->moreLanguageIds) as $language_id) {
      $language = ConfigurableLanguage::createFromLangcode($language_id);
      $language->save();
      $languages[$language->id()] = $language;
    }

    /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
    $backend = Server::load($this->serverId)->getBackend();
    $connector = $backend->getSolrConnector();
    $targeted_solr_major_version = (int) $connector->getSchemaTargetedSolrBranch();
    $language_ids = $this->languageIds + $this->moreLanguageIds;
    if (version_compare($targeted_solr_major_version, '9', '<')) {
      // 'et' requires Solr 8.2, the jump-start-config targets 8.0.
      $language_ids['et'] = FALSE;
      if (version_compare($targeted_solr_major_version, '8', '<')) {
        // 'ga' requires Solr 7.7, the jump-start-config targets 7.0.
        $language_ids['ga'] = FALSE;
        // 'ko' requires Solr 7.5, the jump-start-config targets 7.0.
        $language_ids['ko'] = FALSE;
        if (version_compare($targeted_solr_major_version, '7', '<')) {
          $language_ids['bg'] = FALSE;
          $language_ids['ca'] = FALSE;
          $language_ids['da'] = FALSE;
          $language_ids['fa'] = FALSE;
          $language_ids['hi'] = FALSE;
          $language_ids['hr'] = FALSE;
          $language_ids['id'] = FALSE;
          $language_ids['lv'] = FALSE;
          $language_ids['nb'] = FALSE;
          $language_ids['nn'] = FALSE;
          $language_ids['pl'] = FALSE;
          $language_ids['pt-br'] = FALSE;
          $language_ids['pt-pt'] = FALSE;
          $language_ids['ro'] = FALSE;
          $language_ids['sr'] = FALSE;
          $language_ids['sv'] = FALSE;
          $language_ids['th'] = FALSE;
          $language_ids['tr'] = FALSE;
          $language_ids['zh-hans'] = FALSE;
          $language_ids['zh-hant'] = FALSE;
          if (version_compare($targeted_solr_major_version, '6', '<')) {
            $language_ids['ar'] = FALSE;
            $language_ids['cy'] = FALSE;
            $language_ids['ja'] = FALSE;
            $language_ids['hu'] = FALSE;
            $language_ids['sk'] = FALSE;
            if (version_compare($targeted_solr_major_version, '5', '<')) {
              $language_ids['cs'] = FALSE;
            }
          }
        }
      }
    }
    $language_ids[LanguageInterface::LANGCODE_NOT_SPECIFIED] = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    $this->assertEquals($language_ids, $backend->getSchemaLanguageStatistics());

    foreach ($languages as $language) {
      $language->delete();
    }
  }

  /**
   * Tests the conversion of Search API queries into Solr queries.
   */
  protected function checkQueryParsers() {
    $parse_mode_manager = \Drupal::service('plugin.manager.search_api.parse_mode');
    $parse_mode_terms = $parse_mode_manager->createInstance('terms');
    $parse_mode_phrase = $parse_mode_manager->createInstance('phrase');
    $parse_mode_sloppy_terms = $parse_mode_manager->createInstance('sloppy_terms');
    $parse_mode_sloppy_phrase = $parse_mode_manager->createInstance('sloppy_phrase');
    $parse_mode_fuzzy_terms = $parse_mode_manager->createInstance('fuzzy_terms');
    $parse_mode_direct = $parse_mode_manager->createInstance('direct');
    $parse_mode_edismax = $parse_mode_manager->createInstance('edismax');

    $query = $this->buildSearch('foo "apple pie" bar');
    $query->setParseMode($parse_mode_phrase);
    $flat = SolrUtility::flattenKeys(
      $query->getKeys(),
      [],
      'phrase'
    );
    $this->assertEquals('(+"foo \"apple pie\" bar")', $flat);

    $query->setParseMode($parse_mode_sloppy_terms);
    $flat = SolrUtility::flattenKeys(
      $query->getKeys(),
      [],
      'sloppy_terms',
      ['slop' => 1234]
    );
    $this->assertEquals('(+"foo" +"apple pie"~1234 +"bar")', $flat);

    $query->setParseMode($parse_mode_sloppy_phrase);
    $flat = SolrUtility::flattenKeys(
      $query->getKeys(),
      [],
      'sloppy_phrase',
      ['slop' => 5678]
    );
    $this->assertEquals('(+"foo \"apple pie\" bar"~5678)', $flat);

    $query->setParseMode($parse_mode_fuzzy_terms);
    $flat = SolrUtility::flattenKeys(
      $query->getKeys(),
      [],
      'fuzzy_terms',
      ['fuzzy' => 1]
    );
    $this->assertEquals('(+foo~1 +"apple pie" +bar~1)', $flat);

    $query->setParseMode($parse_mode_terms);
    $flat = SolrUtility::flattenKeys(
      $query->getKeys(),
      [],
      'terms'
    );
    $this->assertEquals('(+"foo" +"apple pie" +"bar")', $flat);

    $query->setParseMode($parse_mode_edismax);
    $exception = FALSE;
    try {
      $flat = SolrUtility::flattenKeys(
        $query->getKeys(),
        [],
        'edismax'
      );
    }
    catch (SearchApiSolrException $e) {
      $exception = TRUE;
    }
    $this->assertTrue($exception);

    $query->setParseMode($parse_mode_direct);
    $exception = FALSE;
    try {
      $flat = SolrUtility::flattenKeys(
        $query->getKeys(),
        [],
        'direct'
      );
    }
    catch (SearchApiSolrException $e) {
      $exception = TRUE;
    }
    $this->assertTrue($exception);

    $query->setParseMode($parse_mode_phrase);
    $flat = SolrUtility::flattenKeys(
      $query->getKeys(),
      ['solr_field'],
      'phrase'
    );
    $this->assertEquals('solr_field:(+"foo \"apple pie\" bar")', $flat);

    $query->setParseMode($parse_mode_terms);
    $flat = SolrUtility::flattenKeys(
      $query->getKeys(),
      ['solr_field'],
      'terms'
    );
    $this->assertEquals('((+(solr_field:"foo") +(solr_field:"apple pie") +(solr_field:"bar")) solr_field:(+"foo" +"apple pie" +"bar"))', $flat);

    $query->setParseMode($parse_mode_edismax);
    $flat = SolrUtility::flattenKeys(
      $query->getKeys(),
      ['solr_field'],
      'edismax'
    );
    $this->assertEquals('({!edismax qf=\'solr_field\'}+"foo" +"apple pie" +"bar")', $flat);

    $query->setParseMode($parse_mode_phrase);
    $flat = SolrUtility::flattenKeys(
      $query->getKeys(),
      ['solr_field_1', 'solr_field_2'],
      'phrase'
    );
    $this->assertEquals('(solr_field_1:(+"foo \"apple pie\" bar") solr_field_2:(+"foo \"apple pie\" bar"))', $flat);

    $query->setParseMode($parse_mode_terms);
    $flat = SolrUtility::flattenKeys(
      $query->getKeys(),
      ['solr_field_1', 'solr_field_2'],
      'terms'
    );
    $this->assertEquals('((+(solr_field_1:"foo" solr_field_2:"foo") +(solr_field_1:"apple pie" solr_field_2:"apple pie") +(solr_field_1:"bar" solr_field_2:"bar")) solr_field_1:(+"foo" +"apple pie" +"bar") solr_field_2:(+"foo" +"apple pie" +"bar"))', $flat);

    $query->setParseMode($parse_mode_edismax);
    $flat = SolrUtility::flattenKeys(
      $query->getKeys(),
      ['solr_field_1', 'solr_field_2'],
      'edismax'
    );
    $this->assertEquals('({!edismax qf=\'solr_field_1 solr_field_2\'}+"foo" +"apple pie" +"bar")', $flat);

    $query->setParseMode($parse_mode_terms);
    $flat = SolrUtility::flattenKeys(
      $query->getKeys(),
      [],
      'keys'
    );
    $this->assertEquals('+"foo" +"apple pie" +"bar"', $flat);

    $query = $this->buildSearch('foo apple pie bar');
    $query->setParseMode($parse_mode_sloppy_phrase);
    $flat = SolrUtility::flattenKeys(
      $query->getKeys(),
      [],
      'sloppy_phrase',
      ['slop' => 5]
    );
    $this->assertEquals('(+"foo apple pie bar"~5)', $flat);

  }

  /**
   * Tests the conversion of Search API queries into Solr queries.
   */
  protected function checkQueryConditions() {
    /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
    $backend = Server::load($this->serverId)->getBackend();
    $options = [];

    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $query->addCondition('id', 5, '=');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('its_id:"5"', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);

    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $query->addCondition('id', 5, '<>');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('(*:* -its_id:"5")', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);

    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $query->addCondition('id', 3, '<>');
    $query->addCondition('id', 5, '<>');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('(*:* -its_id:"3")', $fq[0]['query']);
    $this->assertEquals('(*:* -its_id:"5")', $fq[1]['query']);

    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $condition_group = $query->createConditionGroup();
    $condition_group->addCondition('id', 3, '<>');
    $condition_group->addCondition('id', 5, '<>');
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('(+(*:* -its_id:"3") +(*:* -its_id:"5"))', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);

    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $condition_group = $query->createConditionGroup();
    $condition_group->addCondition('id', 5, '<>');
    $condition_group->addCondition('type', 3);
    $condition_group->addCondition('category', 7);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('(+(*:* -its_id:"5") +ss_type:"3" +ss_category:"7")', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);

    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $condition_group = $query->createConditionGroup();
    $inner_condition_group = $query->createConditionGroup('OR');
    $condition_group->addCondition('id', 5, '<>');
    $inner_condition_group->addCondition('type', 3);
    $inner_condition_group->addCondition('category', 7);
    $condition_group->addConditionGroup($inner_condition_group);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('(+(*:* -its_id:"5") +(ss_type:"3" ss_category:"7"))', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);

    // Condition groups with null value queries are special snowflakes.
    // @see https://www.drupal.org/node/2888629
    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $condition_group = $query->createConditionGroup();
    $inner_condition_group = $query->createConditionGroup('OR');
    $condition_group->addCondition('id', 5, '<>');
    $inner_condition_group->addCondition('type', 3);
    $inner_condition_group->addCondition('category', NULL);
    $condition_group->addConditionGroup($inner_condition_group);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('(+(*:* -its_id:"5") +(ss_type:"3" (*:* -ss_category:[* TO *])))', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);

    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $condition_group = $query->createConditionGroup();
    $inner_condition_group_or = $query->createConditionGroup('OR');
    $inner_condition_group_or->addCondition('id', 3);
    $inner_condition_group_or->addCondition('type', 7, '<>');
    $inner_condition_group_and = $query->createConditionGroup();
    $inner_condition_group_and->addCondition('id', 1);
    $inner_condition_group_and->addCondition('type', 2, '<>');
    $inner_condition_group_and->addCondition('category', 5, '<');
    $condition_group->addConditionGroup($inner_condition_group_or);
    $condition_group->addConditionGroup($inner_condition_group_and);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('(+(its_id:"3" (*:* -ss_type:"7")) +(+its_id:"1" +(*:* -ss_type:"2") +ss_category:{* TO "5"}))', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);

    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $condition_group = $query->createConditionGroup();
    $condition_group->addCondition('id', 5);
    $condition_group->addCondition('type', [1, 2, 3], 'NOT IN');
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('(+its_id:"5" +(*:* -ss_type:("1" "2" "3")))', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);

    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $condition_group = $query->createConditionGroup();
    $condition_group->addCondition('id', 5);
    $inner_condition_group = $query->createConditionGroup();
    $inner_condition_group->addCondition('type', [1, 2, 3], 'NOT IN');
    $condition_group->addConditionGroup($inner_condition_group);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('(+its_id:"5" +(*:* -ss_type:("1" "2" "3")))', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);

    // Test tagging of a single filter query of a facet query.
    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $conditions = $query->createConditionGroup('OR', ['facet:' . 'tagtosearchfor']);
    $conditions->addCondition('category', 'article_category');
    $query->addConditionGroup($conditions);
    $conditions = $query->createConditionGroup('AND');
    $conditions->addCondition('category', NULL, '<>');
    $query->addConditionGroup($conditions);
    $facets['category'] = [
      'field' => 'category',
      'limit' => 0,
      'min_count' => 1,
      'missing' => TRUE,
      'operator' => 'or',
    ];
    $query->setOption('search_api_facets', $facets);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('ss_category:"article_category"', $fq[0]['query'], 'Condition found in tagged first filter query');
    $this->assertEquals(['facet:tagtosearchfor' => 'facet:tagtosearchfor'], $fq[0]['tags'], 'Tag found in tagged first filter query');
    $this->assertEquals('ss_category:[* TO *]', $fq[1]['query'], 'Condition found in unrelated second filter query');
    $this->assertEquals([], $fq[1]['tags'], 'No tag found in second filter query');

    // @see https://www.drupal.org/node/2753917
    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $conditions = $query->createConditionGroup('OR', ['facet:id']);
    $conditions->addCondition('id', '27');
    $conditions->addCondition('id', '28');
    $query->addConditionGroup($conditions);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals(1, count($fq));
    $this->assertEquals(['facet:id' => 'facet:id'], $fq[0]['tags']);
    $this->assertEquals('(its_id:"27" its_id:"28")', $fq[0]['query']);

    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $conditions = $query->createConditionGroup('AND', ['facet:id']);
    $conditions->addCondition('id', '27');
    $conditions->addCondition('id', '28');
    $query->addConditionGroup($conditions);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals(1, count($fq));
    $this->assertEquals(['facet:id' => 'facet:id'], $fq[0]['tags']);
    $this->assertEquals('(+its_id:"27" +its_id:"28")', $fq[0]['query']);

    $query = $this->buildSearch();
    $query->setLanguages(['en']);
    $query->addCondition('id', 5, '=');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('its_id:"5"', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);

    $query = $this->buildSearch();
    $query->setLanguages(['en', 'de']);
    $condition_group = $query->createConditionGroup();
    $condition_group->addCondition('id', 5);
    $inner_condition_group = $query->createConditionGroup();
    $inner_condition_group->addCondition('type', [1, 2, 3], 'NOT IN');
    $condition_group->addConditionGroup($inner_condition_group);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('(+its_id:"5" +(*:* -ss_type:("1" "2" "3")))', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);

    $query = $this->buildSearch();
    $query->setLanguages(['en']);
    $condition_group = $query->createConditionGroup();
    $condition_group->addCondition('id', 5);
    $condition_group->addCondition('search_api_language', 'de');
    $inner_condition_group = $query->createConditionGroup();
    $inner_condition_group->addCondition('type', [1, 2, 3], 'NOT IN');
    $condition_group->addConditionGroup($inner_condition_group);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('(+its_id:"5" +ss_search_api_language:"de" +(*:* -ss_type:("1" "2" "3")))', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);

    $query = $this->buildSearch();
    $query->setLanguages(['en']);
    $query->addCondition('body', 'some text', '=');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('tm_X3b_en_body:("some text")', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);

    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $query->addCondition('body', 'some text', '=');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('tm_X3b_und_body:("some text")', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);

    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_APPLICABLE]);
    $query->addCondition('body', 'some text', '=');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('tm_X3b_und_body:("some text")', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);

    $parse_mode_manager = \Drupal::service('plugin.manager.search_api.parse_mode');
    $parse_mode_phrase = $parse_mode_manager->createInstance('phrase');

    $query = $this->buildSearch();
    $query->setLanguages(['en']);
    $query->setParseMode($parse_mode_phrase);
    $query->addCondition('body', 'some text', '=');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('tm_X3b_en_body:("some text")', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);

    $query = $this->buildSearch();
    $query->setLanguages(['en']);
    $query->setParseMode($parse_mode_phrase);
    $query->addCondition('body', ['some', 'text'], '=');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('tm_X3b_en_body:("some" "text")', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);

    $query = $this->buildSearch();
    $query->addCondition('changed', '2024-03-04', '=');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('ds_changed:"2024-03-04T00:00:00Z"', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);

    $query = $this->buildSearch();
    $query->addCondition('changed', '*', '=');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('ds_changed:*', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);

    $query = $this->buildSearch();
    $query->addCondition('changed', NULL, '=');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('(*:* -ds_changed:[* TO *])', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);

    $query = $this->buildSearch();
    $query->addCondition('changed', ['2024-03-04', '2024-03-20'], 'BETWEEN');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('ds_changed:["2024-03-04T00:00:00Z" TO "2024-03-20T00:00:00Z"]', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);

    $query = $this->buildSearch();
    $query->addCondition('changed', ['*', '2024-03-20'], 'BETWEEN');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('ds_changed:[* TO "2024-03-20T00:00:00Z"]', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);

    $query = $this->buildSearch();
    $query->addCondition('changed', ['2024-03-04', '*'], 'BETWEEN');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('ds_changed:["2024-03-04T00:00:00Z" TO *]', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);

    $query = $this->buildSearch();
    $query->addCondition('changed', [NULL, '2024-03-20'], 'BETWEEN');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('ds_changed:[* TO "2024-03-20T00:00:00Z"]', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);

    $query = $this->buildSearch();
    $query->addCondition('changed', ['2024-03-04', NULL], 'BETWEEN');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('ds_changed:["2024-03-04T00:00:00Z" TO *]', $fq[0]['query']);
    $this->assertArrayNotHasKey(1, $fq);
  }

  /**
   * Tests retrieve_data options.
   */
  protected function checkRetrieveData() {
    $server = $this->getIndex()->getServerInstance();
    $config = $server->getBackendConfig();
    $backend = $server->getBackend();

    $this->indexItems($this->indexId);

    // Retrieve just required fields.
    $query = $this->buildSearch('foobar');
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Search for »foobar« returned correct number of results.');
    /** @var \Drupal\search_api\Item\ItemInterface $result */
    foreach ($results as $result) {
      /** @var \Solarium\QueryType\Select\Result\Document $solr_document */
      $solr_document = $result->getExtraData('search_api_solr_document', NULL);
      $fields = $solr_document->getFields();
      $this->assertEquals('entity:entity_test_mulrev_changed/3:en', $fields['ss_search_api_id']);
      $this->assertEquals('en', $fields['ss_search_api_language']);
      $this->assertArrayHasKey('score', $fields);
      $this->assertArrayNotHasKey('tm_X3b_en_body', $fields);
      $this->assertArrayNotHasKey('id', $fields);
      $this->assertArrayNotHasKey('its_id', $fields);
      $this->assertArrayNotHasKey('twm_suggest', $fields);
    }

    // Retrieve all fields.
    $config['retrieve_data'] = TRUE;
    $server->setBackendConfig($config);
    $server->save();

    $query = $this->buildSearch('foobar');
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Search for »foobar« returned correct number of results.');
    /** @var \Drupal\search_api\Item\ItemInterface $result */
    foreach ($results as $result) {
      /** @var \Solarium\QueryType\Select\Result\Document $solr_document */
      $solr_document = $result->getExtraData('search_api_solr_document', NULL);
      $fields = $solr_document->getFields();
      $this->assertEquals('entity:entity_test_mulrev_changed/3:en', $fields['ss_search_api_id']);
      $this->assertEquals('en', $fields['ss_search_api_language']);
      $this->assertArrayHasKey('score', $fields);
      $this->assertArrayHasKey('tm_X3b_en_body', $fields);
      $this->assertStringContainsString('search_index-entity:entity_test_mulrev_changed/3:en', $fields['id']);
      $this->assertEquals('3', $fields['its_id']);
      $this->assertArrayHasKey('twm_suggest', $fields);
    }

    // Retrieve list of fields in addition to required fields.
    $query = $this->buildSearch('foobar');
    $query->setOption('search_api_retrieved_field_values', ['body' => 'body']);
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Search for »foobar« returned correct number of results.');
    /** @var \Drupal\search_api\Item\ItemInterface $result */
    foreach ($results as $result) {
      /** @var \Solarium\QueryType\Select\Result\Document $solr_document */
      $solr_document = $result->getExtraData('search_api_solr_document', NULL);
      $fields = $solr_document->getFields();
      $this->assertEquals('entity:entity_test_mulrev_changed/3:en', $fields['ss_search_api_id']);
      $this->assertEquals('en', $fields['ss_search_api_language']);
      $this->assertArrayHasKey('score', $fields);
      $this->assertArrayHasKey('tm_X3b_en_body', $fields);
      $this->assertArrayNotHasKey('id', $fields);
      $this->assertArrayNotHasKey('its_id', $fields);
      $this->assertArrayNotHasKey('twm_suggest', $fields);
    }

    $fulltext_fields = array_flip($this->invokeMethod($backend, 'getQueryFulltextFields', [$query]));
    $this->assertArrayHasKey('name', $fulltext_fields);
    $this->assertArrayHasKey('body', $fulltext_fields);
    $this->assertArrayHasKey('body_unstemmed', $fulltext_fields);
    $this->assertArrayHasKey('category_edge', $fulltext_fields);
    // body_suggest should be removed by getQueryFulltextFields().
    $this->assertArrayNotHasKey('body_suggest', $fulltext_fields);
  }

  /**
   * Tests retrieve_data options.
   */
  protected function checkIndexFallback() {
    global $_search_api_solr_test_index_fallback_test;

    // If set to TRUE, search_api_solr_test_search_api_solr_documents_alter()
    // turns one out of five test documents into an illegal one.
    $_search_api_solr_test_index_fallback_test = TRUE;

    // If five documents are updated as batch, one illegal document causes the
    // entire batch to fail.
    $this->assertEquals($this->indexItems($this->indexId), 0);

    // Enable the fallback to index the documents one by one.
    $server = $this->getIndex()->getServerInstance();
    $config = $server->getBackendConfig();
    $config['index_single_documents_fallback_count'] = 10;
    $server->setBackendConfig($config);
    $server->save();

    // Indexed one by one, four documents get indexed successfully.
    $this->assertEquals($this->indexItems($this->indexId), 4);

    // Don't mess up the remaining document anymore.
    $_search_api_solr_test_index_fallback_test = FALSE;
    // Disable the fallback to index the documents one by one.
    $config['index_single_documents_fallback_count'] = 0;
    $server->setBackendConfig($config);
    $server->save();

    // Index the previously broken document that is still in the queue.
    $this->assertEquals($this->indexItems($this->indexId), 1);
  }

  /**
   * Tests highlight options.
   */
  protected function checkHighlight() {
    $server = $this->getIndex()->getServerInstance();
    $config = $server->getBackendConfig();

    $this->indexItems($this->indexId);

    $query = $this->buildSearch('foobar');
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Search for »foobar« returned correct number of results.');
    /** @var \Drupal\search_api\Item\ItemInterface $result */
    foreach ($results as $result) {
      $this->assertEmpty($result->getExtraData('highlighted_fields', []));
      $this->assertEmpty($result->getExtraData('highlighted_keys', []));
    }

    $config['highlight_data'] = TRUE;
    $server->setBackendConfig($config);
    $server->save();

    $query = $this->buildSearch('foobar');
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Search for »foobar« returned correct number of results.');
    /** @var \Drupal\search_api\Item\ItemInterface $result */
    foreach ($results as $result) {
      $this->assertStringContainsString('<strong>foobar</strong>', (string) $result->getExtraData('highlighted_fields', ['body' => ['']])['body'][0]);
      $this->assertEquals(['foobar'], $result->getExtraData('highlighted_keys', []));
      $this->assertEquals('… bar … test <strong>foobar</strong> Case …', $result->getExcerpt());
    }

    // Test highlghting with stemming.
    $query = $this->buildSearch('foobars');
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Search for »foobar« returned correct number of results.');
    /** @var \Drupal\search_api\Item\ItemInterface $result */
    foreach ($results as $result) {
      $this->assertStringContainsString('<strong>foobar</strong>', (string) $result->getExtraData('highlighted_fields', ['body' => ['']])['body'][0]);
      $this->assertEquals(['foobar'], $result->getExtraData('highlighted_keys', []));
      $this->assertEquals('… bar … test <strong>foobar</strong> Case …', $result->getExcerpt());
    }
  }

  /**
   * Test that basic auth config gets passed to Solarium.
   */
  protected function checkBasicAuth() {
    $server = $this->getServer();
    $config = $server->getBackendConfig();
    $config['connector_config']['username'] = 'foo';
    $config['connector_config']['password'] = 'bar';
    $server->setBackendConfig($config);
    /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
    $backend = $server->getBackend();
    $auth = $backend->getSolrConnector()->getEndpoint()->getAuthentication();
    $this->assertEquals(['username' => 'foo', 'password' => 'bar'], $auth);

    $config['connector_config']['username'] = '';
    $config['connector_config']['password'] = '';
    $server->setBackendConfig($config);
  }

  /**
   * Tests addition and deletion of a data source.
   */
  protected function checkDatasourceAdditionAndDeletion() {
    $this->indexItems($this->indexId);

    $results = $this->buildSearch()->execute();
    $this->assertEquals(5, $results->getResultCount(), 'Number of indexed entities is correct.');

    try {
      $results = $this->buildSearch()->addCondition('uid', 0, '>')->execute();
      $this->fail('Field uid must not yet exists in this index.');
    }
    catch (\Exception $e) {
      // An expected exception occurred.
    }

    $index = $this->getIndex();
    $index->set('datasource_settings', $index->get('datasource_settings') + [
      'entity:user' => [],
    ]);
    $info = [
      'label' => 'uid',
      'type' => 'integer',
      'datasource_id' => 'entity:user',
      'property_path' => 'uid',
    ];
    $index->addField($this->fieldsHelper->createField($index, 'uid', $info));
    $index->save();

    User::create([
      'uid' => 1,
      'name' => 'root',
      'langcode' => 'en',
    ])->save();

    $this->indexItems($this->indexId);

    $results = $this->buildSearch()->execute();
    $this->assertEquals(6, $results->getResultCount(), 'Number of indexed entities in multi datasource index is correct.');

    $results = $this->buildSearch()->addCondition('uid', 0, '>')->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Search for users returned correct number of results.');

    $index = $this->getIndex();
    $index->removeDatasource('entity:user')->save();

    $this->ensureCommit($index);

    $results = $this->buildSearch()->execute();
    $this->assertEquals(5, $results->getResultCount(), 'Number of indexed entities is correct.');

    try {
      $results = $this->buildSearch()->addCondition('uid', 0, '>')->execute();
      $this->fail('Field uid must not yet exists in this index.');
    }
    catch (\Exception $e) {
      $this->assertEquals('An error occurred while searching, try again later.', $e->getMessage());
    }
  }

  /**
   * Produces a string of given comprising diverse chars.
   *
   * @param int $length
   *   Length of the string.
   *
   * @return string
   *   A random string of the specified length.
   */
  protected function getLongText($length) {
    $sequence = 'abcdefghijklmnopqrstuwxyz1234567890,./;\'[]\\<>?:"{}|~!@#$%^&*()_+`1234567890-=ööążźćęółńABCDEFGHIJKLMNOPQRSTUWXYZ';
    $result = '';
    $i = 0;

    $sequenceLength = strlen($sequence);
    while ($i++ != $length) {
      $result .= $sequence[$i % $sequenceLength];
    }

    return $result;
  }

  /**
   * Tests search result grouping.
   */
  public function checkSearchResultGrouping() {
    if (in_array('search_api_grouping', $this->getIndex()->getServerInstance()->getBackend()->getSupportedFeatures())) {
      $query = $this->buildSearch(NULL, [], [], FALSE);
      $query->setOption('search_api_grouping', [
        'use_grouping' => TRUE,
        'fields' => [
          'type',
        ],
      ]);
      $results = $query->execute();

      $this->assertEquals(2, $results->getResultCount(), 'Get the results count grouping by type.');
      $data = $results->getExtraData('search_api_solr_response');
      $this->assertEquals(5, $data['grouped']['ss_type']['matches'], 'Get the total documents after grouping.');
      $this->assertEquals(2, $data['grouped']['ss_type']['ngroups'], 'Get the number of groups after grouping.');
      $this->assertResults([1, 4], $results, 'Grouping by type');
    }
    else {
      $this->markTestSkipped("The selected backend/connector doesn't support the *search_api_grouping* feature.");
    }
  }

  /**
   * Tests search result sorts.
   */
  protected function checkSearchResultSorts() {
    // Add node with body length just above the solr limit for search fields.
    // It's exceeded by just a single char to simulate an edge case.
    $this->addTestEntity(6, [
      'name' => 'Long text',
      'body' => $this->getLongText(32767),
      'type' => 'article',
    ]);

    // Add another node with body length equal to the limit.
    $this->addTestEntity(7, [
      'name' => 'Z long',
      'body' => $this->getLongText(32766),
      'type' => 'article',
    ]);

    $this->indexItems($this->indexId);

    // Type text.
    $results = $this->buildSearch(NULL, [], [], FALSE)
      ->sort('name')
      // Force an expected order for identical names.
      ->sort('search_api_id')
      ->execute();
    $this->assertResults([3, 5, 1, 4, 2, 6, 7], $results, 'Sort by name.');

    $results = $this->buildSearch(NULL, [], [], FALSE)
      ->sort('name', QueryInterface::SORT_DESC)
      // Force an expected order for identical names.
      ->sort('search_api_id')
      ->execute();
    $this->assertResults([7, 6, 2, 4, 1, 5, 3], $results, 'Sort by name descending.');

    // Type string.
    $results = $this->buildSearch(NULL, [], [], FALSE)
      ->sort('type')
      // Force an expected order for identical types.
      ->sort('search_api_id')
      ->execute();
    $this->assertResults([4, 5, 6, 7, 1, 2, 3], $results, 'Sort by type.');

    $results = $this->buildSearch(NULL, [], [], FALSE)
      ->sort('type', QueryInterface::SORT_DESC)
      // Force an expected order for identical types.
      ->sort('search_api_id')
      ->execute();
    $this->assertResults([1, 2, 3, 4, 5, 6, 7], $results, 'Sort by type descending.');

    /** @var \Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend $backend */
    $backend = Server::load($this->serverId)->getBackend();
    $targeted_branch = $backend->getSolrConnector()->getSchemaTargetedSolrBranch();
    if ('3.x' !== $targeted_branch) {
      // There's no real collated field for Solr 3.x. Therefore, the sorting of
      // "non-existing" values differ.
      // Type multi-value string. Uses first value.
      $results = $this->buildSearch(NULL, [], [], FALSE)
        ->sort('keywords')
        // Force an expected order for identical keywords.
        ->sort('search_api_id')
        ->execute();
      $this->assertResults([3, 6, 7, 4, 1, 2, 5], $results, 'Sort by keywords.');

      $results = $this->buildSearch(NULL, [], [], FALSE)
        ->sort('keywords', QueryInterface::SORT_DESC)
        // Force an expected order for identical keywords.
        ->sort('search_api_id')
        ->execute();
      $this->assertResults([1, 2, 5, 4, 3, 6, 7], $results, 'Sort by keywords descending.');

      // Type decimal.
      $results = $this->buildSearch(NULL, [], [], FALSE)
        ->sort('width')
        // Force an expected order for identical width.
        ->sort('search_api_id')
        ->execute();
      $this->assertResults([1, 2, 3, 6, 7, 4, 5], $results, 'Sort by width.');

      $results = $this->buildSearch(NULL, [], [], FALSE)
        ->sort('width', QueryInterface::SORT_DESC)
        // Force an expected order for identical width.
        ->sort('search_api_id')
        ->execute();
      $this->assertResults([5, 4, 1, 2, 3, 6, 7], $results, 'Sort by width descending.');
    }

    $results = $this->buildSearch(NULL, [], [], FALSE)
      ->sort('changed')
      ->execute();
    $this->assertResults([1, 2, 4, 5, 3, 6, 7], $results, 'Sort by last update date');

    $results = $this->buildSearch(NULL, [], [], FALSE)
      ->sort('changed', QueryInterface::SORT_DESC)
      ->execute();
    $this->assertResults([7, 6, 3, 5, 4, 2, 1], $results, 'Sort by last update date descending');

    $this->removeTestEntity(6);
    $this->removeTestEntity(7);
  }

  /**
   * Tests the ngram result.
   */
  protected function testNgram(): void {
    $this->addTestEntity(1, [
      'name' => 'Test Article 1',
      'body' => 'The test article number 1 about cats, dogs and trees.',
      'type' => 'article',
      'category' => 'dogs and trees',
    ]);

    // Add another node with body length equal to the limit.
    $this->addTestEntity(2, [
      'name' => 'Test Article 1',
      'body' => 'The test article number 2 about a tree.',
      'type' => 'article',
      'category' => 'trees',
    ]);

    $this->indexItems($this->indexId);

    // Tests NGram and Edge NGram search result.
    foreach (['category_ngram', 'category_edge'] as $field) {
      $results = $this->buildSearch(['tre'], [], [$field])
        ->execute();
      $this->assertResults([1, 2], $results, $field . ': tre');

      $results = $this->buildSearch(['Dog'], [], [$field])
        ->execute();
      $this->assertResults([1], $results, $field . ': Dog');

      $results = $this->buildSearch([], [], [])
        ->addCondition($field, 'Dog')
        ->execute();
      $this->assertResults([1], $results, $field . ': Dog as condition');
    }

    // Tests NGram search result.
    $result_set = [
      'category_ngram' => [1, 2],
      'category_ngram_string' => [1, 2],
      'category_edge' => [],
      'category_edge_string' => [],
    ];
    foreach ($result_set as $field => $expected_results) {
      $results = $this->buildSearch(['re'], [], [$field])
        ->execute();
      $this->assertResults($expected_results, $results, $field . ': re');
    }

    foreach (['category_ngram_string' => [1, 2], 'category_edge_string' => [2]] as $field => $expected_results) {
      $results = $this->buildSearch(['tre'], [], [$field])
        ->execute();
      $this->assertResults($expected_results, $results, $field . ': tre');
    }
  }

  /**
   * Tests language fallback and language limiting via options.
   */
  public function testLanguageFallbackAndLanguageLimitedByOptions() {
    $this->insertMultilingualExampleContent();
    $this->indexItems($this->indexId);

    $index = $this->getIndex();
    $connector = $index->getServerInstance()->getBackend()->getSolrConnector();

    $results = $this->buildSearch()->execute();
    $this->assertEquals(6, $results->getResultCount(), 'Number of indexed entities is correct.');

    // Stemming "en":
    // gene => gene
    // genes => gene
    //
    // Stemming "de":
    // Gen => gen
    // Gene => gen.
    $query = $this->buildSearch('Gen');
    $query->sort('name');
    $query->setLanguages(['en', 'de']);
    $results = $query->execute();
    $this->assertEquals(2, $results->getResultCount(), 'Two results for "Gen" in German entities. No results for "Gen" in English entities.');
    $params = $connector->getRequestParams();
    $this->assertEquals('ss_search_api_language:("en" "de")', $params['fq'][1]);
    $this->assertEquals('ss_search_api_id asc,sort_X3b_en_name asc', $params['sort'][0]);

    $query = $this->buildSearch('Gene');
    $query->setLanguages(['en', 'de']);
    $results = $query->execute();
    $this->assertEquals(4, $results->getResultCount(), 'Two results for "Gene" in German entities. Two results for "Gene" in English entities.');

    // Stemming of "de-at" should fall back to "de".
    $query = $this->buildSearch('Gen');
    $query->setLanguages(['de-at']);
    $results = $query->execute();
    $this->assertEquals(2, $results->getResultCount(), 'Two results for "Gen" in Austrian entities.');
    $query = $this->buildSearch('Gene');
    $query->setLanguages(['de-at']);
    $results = $query->execute();
    $this->assertEquals(2, $results->getResultCount(), 'Two results for "Gene" in Austrian entities.');
    $params = $connector->getRequestParams();
    $this->assertEquals('ss_search_api_language:"de-at"', $params['fq'][1]);

    $settings = $index->getThirdPartySettings('search_api_solr');
    $settings['multilingual']['limit_to_content_language'] = FALSE;
    $settings['multilingual']['include_language_independent'] = FALSE;
    $index->setThirdPartySetting('search_api_solr', 'multilingual', $settings['multilingual']);
    $index->save();
    $this->assertFalse($this->getIndex()->getThirdPartySetting('search_api_solr', 'multilingual')['limit_to_content_language']);
    $this->assertFalse($this->getIndex()->getThirdPartySetting('search_api_solr', 'multilingual')['include_language_independent']);

    // Stemming "en":
    // gene => gene
    // genes => gene
    //
    // Stemming "de":
    // Gen => gen
    // Gene => gen.
    $results = $this->buildSearch('gene', [], ['body'])->execute();
    $expected_results = [
      1 => 'en',
      2 => 'en',
      3 => 'de',
      4 => 'de',
      5 => 'de-at',
      6 => 'de-at',
    ];
    $this->assertResults($expected_results, $results, 'Search all languages for "gene".');

    $settings['multilingual']['limit_to_content_language'] = TRUE;
    $index->setThirdPartySetting('search_api_solr', 'multilingual', $settings['multilingual']);
    $index->save();
    $this->assertTrue($this->getIndex()->getThirdPartySetting('search_api_solr', 'multilingual')['limit_to_content_language']);

    // Current content language is "en".
    $results = $this->buildSearch('gene', [], ['body'])->execute();
    $expected_results = [
      1 => 'en',
      2 => 'en',
    ];
    $this->assertResults($expected_results, $results, 'Search content language for "gene".');

    // A query created by Views must not be overruled.
    $results = $this->buildSearch('gene', [], ['body'])->addTag('views')->execute();
    $expected_results = [
      1 => 'en',
      2 => 'en',
      3 => 'de',
      4 => 'de',
      5 => 'de-at',
      6 => 'de-at',
    ];
    $this->assertResults($expected_results, $results, 'Search all languages for "gene".');

    $settings['multilingual']['include_language_independent'] = TRUE;
    $index->setThirdPartySetting('search_api_solr', 'multilingual', $settings['multilingual']);
    $index->save();
    $this->assertTrue($this->getIndex()->getThirdPartySetting('search_api_solr', 'multilingual')['include_language_independent']);

    $results = $this->buildSearch('gene', [], ['body'])->execute();
    $expected_results = [
      1 => 'en',
      2 => 'en',
      7 => LanguageInterface::LANGCODE_NOT_APPLICABLE,
    ];
    $this->assertResults($expected_results, $results, 'Search content and unspecified language for "gene".');

    $settings['multilingual']['limit_to_content_language'] = FALSE;
    $index->setThirdPartySetting('search_api_solr', 'multilingual', $settings['multilingual']);
    $index->save();
    $this->assertFalse($this->getIndex()->getThirdPartySetting('search_api_solr', 'multilingual')['limit_to_content_language']);

    $results = $this->buildSearch('gene', [], ['body'])->execute();
    $expected_results = [
      1 => 'en',
      2 => 'en',
      3 => 'de',
      4 => 'de',
      5 => 'de-at',
      6 => 'de-at',
      7 => LanguageInterface::LANGCODE_NOT_APPLICABLE,
    ];
    $this->assertResults($expected_results, $results, 'Search all and unspecified languages for "gene".');

    $results = $this->buildSearch('und', [], ['name'])->execute();
    $expected_results = [
      7 => LanguageInterface::LANGCODE_NOT_APPLICABLE,
      8 => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ];
    $this->assertResults($expected_results, $results, 'Search all and unspecified languages for "und".');

    $this->assertFalse($this->getIndex()->isReindexing());
    ConfigurableLanguage::createFromLangcode('de-ch')->save();
    $this->assertTrue($this->getIndex()->isReindexing());
  }

  /**
   * Creates several test entities.
   */
  protected function insertMultilingualExampleContent() {
    $this->addTestEntity(1, [
      'name' => 'en 1',
      'body' => 'gene',
      'type' => 'item',
      'langcode' => 'en',
    ]);
    $this->addTestEntity(2, [
      'name' => 'en 2',
      'body' => 'genes',
      'type' => 'item',
      'langcode' => 'en',
    ]);
    $this->addTestEntity(3, [
      'name' => 'de 3',
      'body' => 'Gen',
      'type' => 'item',
      'langcode' => 'de',
    ]);
    $this->addTestEntity(4, [
      'name' => 'de 4',
      'body' => 'Gen',
      'type' => 'item',
      'langcode' => 'de',
    ]);
    $this->addTestEntity(5, [
      'name' => 'de-at 5',
      'body' => 'Gen',
      'type' => 'item',
      'langcode' => 'de-at',
    ]);
    $this->addTestEntity(6, [
      'name' => 'de-at 6',
      'body' => 'Gen',
      'type' => 'item',
      'langcode' => 'de-at',
    ]);
    $this->addTestEntity(7, [
      'name' => 'und 7',
      'body' => 'gene',
      'type' => 'item',
      'langcode' => LanguageInterface::LANGCODE_NOT_APPLICABLE,
    ]);
    $this->addTestEntity(8, [
      'name' => 'und 8',
      'body' => 'genes',
      'type' => 'item',
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ]);
    $count = \Drupal::entityQuery('entity_test_mulrev_changed')->count()->accessCheck()->execute();
    $this->assertEquals(8, $count, "$count items inserted.");
  }

  /**
   * {@inheritdoc}
   *
   * If the list of entity ids contains language codes it will be handled here,
   * otherwise it will be handed over to the parent implementation.
   *
   * @param array $entity_ids
   *   An array of entity IDs or an array keyed by entity IDs and langcodes as
   *   values.
   *
   * @return string[]
   *   An array of item IDs.
   */
  protected function getItemIds(array $entity_ids) {
    $item_ids = [];
    if (!empty($entity_ids)) {
      $keys = array_keys($entity_ids);
      $first_key = reset($keys);
      if (0 === $first_key) {
        return parent::getItemIds($entity_ids);
      }
      else {
        foreach ($entity_ids as $id => $langcode) {
          $item_ids[] = Utility::createCombinedId('entity:entity_test_mulrev_changed', $id . ':' . $langcode);
        }
      }
    }
    return $item_ids;
  }

  /**
   * Test generation of Solr configuration files.
   *
   * @dataProvider configGenerationDataProvider
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testConfigGeneration(array $files) {
    $server = $this->getServer();
    /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
    $backend = $server->getBackend();
    $solr_major_version = $backend->getSolrConnector()->getSolrMajorVersion();
    $backend_config = $server->getBackendConfig();
    $solr_configset_controller = new SolrConfigSetController(\Drupal::service('extension.list.module'));
    $solr_configset_controller->setServer($server);

    $config_files = $solr_configset_controller->getConfigFiles();

    foreach ($files as $file_name => $expected_strings) {
      $this->assertArrayHasKey($file_name, $config_files);
      foreach ($expected_strings as $string) {
        $this->assertStringContainsString($string, $config_files[$file_name]);
      }
    }

    $config_name = 'name="drupal-' . $backend->getPreferredSchemaVersion() . '-solr-' . $solr_major_version . '.x-' . SEARCH_API_SOLR_JUMP_START_CONFIG_SET . '"';
    $this->assertStringContainsString($config_name, $config_files['solrconfig.xml']);
    $this->assertStringContainsString($config_name, $config_files['schema.xml']);
    $this->assertStringContainsString($server->id(), $config_files['test.txt']);
    $this->assertStringNotContainsString('<jmx />', $config_files['solrconfig_extra.xml']);
    $this->assertStringNotContainsString('JtsSpatialContextFactory', $config_files['schema.xml']);
    if ('true' === SOLR_CLOUD) {
      $this->assertStringContainsString('solr.luceneMatchVersion:' . $solr_major_version, $config_files['solrconfig.xml']);
      $this->assertStringContainsString('<statsCache class="org.apache.solr.search.stats.LRUStatsCache" />', $config_files['solrconfig_extra.xml']);
    }
    else {
      $this->assertStringContainsString('solr.luceneMatchVersion=' . $solr_major_version, $config_files['solrcore.properties']);
      $this->assertStringNotContainsString('<statsCache', $config_files['solrconfig_extra.xml']);
    }

    $backend_config['connector_config']['jmx'] = TRUE;
    $backend_config['connector_config']['jts'] = TRUE;
    $backend_config['disabled_field_types'] = [
      'text_foo_en_4_5_0',
      'text_foo_en_6_0_0',
      'text_de_4_5_0',
      'text_de_6_0_0',
      'text_de_7_0_0',
    ];
    $backend_config['disabled_caches'] = [
      'cache_document_default_7_0_0',
      'cache_filter_default_7_0_0',
      'cache_document_default_9_0_0',
      'cache_filter_default_9_0_0',
    ];
    $server->setBackendConfig($backend_config);
    $server->save();
    // Reset static caches.
    $solr_configset_controller->setServer($server);

    $config_files = $solr_configset_controller->getConfigFiles();
    if (version_compare($solr_major_version, '9', '>=')) {
      $this->assertStringNotContainsString('<jmx />', $config_files['solrconfig_extra.xml']);
    }
    else {
      $this->assertStringContainsString('<jmx />', $config_files['solrconfig_extra.xml']);
    }
    $this->assertStringContainsString('JtsSpatialContextFactory', $config_files['schema.xml']);
    $this->assertStringContainsString('text_en', $config_files['schema_extra_types.xml']);
    $this->assertStringNotContainsString('text_foo_en', $config_files['schema_extra_types.xml']);
    $this->assertStringNotContainsString('text_de', $config_files['schema_extra_types.xml']);
    if (version_compare($solr_major_version, '7', '>=')) {
      $this->assertStringNotContainsString('documentCache', $config_files['solrconfig_query.xml']);
      $this->assertStringNotContainsString('filterCache', $config_files['solrconfig_query.xml']);
      $this->assertStringContainsString('httpCaching', $config_files['solrconfig_requestdispatcher.xml']);
      $this->assertStringContainsString('never304="true"', $config_files['solrconfig_requestdispatcher.xml']);
    }
    else {
      $this->assertStringContainsString('httpCaching', $config_files['solrconfig.xml']);
      $this->assertStringContainsString('never304="true"', $config_files['solrconfig.xml']);
    }
    $this->assertStringContainsString('ts_X3b_en_*', $config_files['schema_extra_fields.xml']);
    $this->assertStringNotContainsString('ts_X3b_de_*', $config_files['schema_extra_fields.xml']);

    /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
    $backend = $server->getBackend();
    if ($backend->getSolrConnector()->isCloud()) {
      $this->assertArrayNotHasKey('solrcore.properties', $config_files);
      $this->assertStringNotContainsString('"/replication"', $config_files['solrconfig_extra.xml']);
      $this->assertStringNotContainsString('"/get"', $config_files['solrconfig_extra.xml']);
    }
    else {
      $this->assertStringNotContainsString('solr.install.dir', $config_files['solrcore.properties']);
      $this->assertStringContainsString('solr.replication', $config_files['solrcore.properties']);
      $this->assertStringContainsString('"/replication"', $config_files[(version_compare($solr_major_version, '7', '>=')) ? 'solrconfig_extra.xml' : 'solrconfig.xml']);
      if (version_compare($solr_major_version, '7', '>=')) {
        $this->assertStringNotContainsString('"/get"', $config_files['solrconfig_extra.xml']);
      }
      else {
        $this->assertStringContainsString('"/get"', $config_files['solrconfig.xml']);
      }
    }
  }

  /**
   * Data provider for testConfigGeneration method.
   */
  public static function configGenerationDataProvider() {
    // @codingStandardsIgnoreStart
    return [[[
      'schema_extra_types.xml' => [
        # phonetic is currently not available for Solr <= 7.x.
        #'fieldType name="text_phonetic_en" class="solr.TextField"',
        'fieldType name="text_en" class="solr.TextField"',
        'fieldType name="text_de" class="solr.TextField"',
        '<fieldType name="collated_und" class="solr.ICUCollationField" locale="" strength="primary" caseLevel="false"/>',
        '<fieldType name="text_foo_en" class="solr.TextField" positionIncrementGap="100">
  <analyzer type="index">
    <tokenizer class="solr.WhitespaceTokenizerFactory"/>
    <filter class="solr.LengthFilterFactory" min="2" max="100"/>
    <filter class="solr.LowerCaseFilterFactory"/>
    <filter class="solr.RemoveDuplicatesTokenFilterFactory"/>
  </analyzer>
  <analyzer type="query">
    <tokenizer class="solr.WhitespaceTokenizerFactory"/>
    <filter class="solr.LengthFilterFactory" min="2" max="100"/>
    <filter class="solr.LowerCaseFilterFactory"/>
    <filter class="solr.RemoveDuplicatesTokenFilterFactory"/>
  </analyzer>',
      ],
      'schema_extra_fields.xml' => [
        # phonetic is currently not available for Solr <= 7.x.
        #'<dynamicField name="tcphonetics_X3b_en_*" type="text_phonetic_en" stored="true" indexed="true" multiValued="false" termVectors="true" omitNorms="false" />',
        #'<dynamicField name="tcphoneticm_X3b_en_*" type="text_phonetic_en" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="false" />',
        #'<dynamicField name="tocphonetics_X3b_en_*" type="text_phonetic_en" stored="true" indexed="true" multiValued="false" termVectors="true" omitNorms="true" />',
        #'<dynamicField name="tocphoneticm_X3b_en_*" type="text_phonetic_en" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="true" />',
        '<dynamicField name="ts_X3b_en_*" type="text_en" stored="true" indexed="true" multiValued="false" termVectors="true" omitNorms="false" />',
        '<dynamicField name="tm_X3b_en_*" type="text_en" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="false" />',
        '<dynamicField name="tos_X3b_en_*" type="text_en" stored="true" indexed="true" multiValued="false" termVectors="true" omitNorms="true" />',
        '<dynamicField name="tom_X3b_en_*" type="text_en" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="true" />',
        '<dynamicField name="tus_X3b_en_*" type="text_unstemmed_en" stored="true" indexed="true" multiValued="false" termVectors="true" omitNorms="false" />',
        '<dynamicField name="tum_X3b_en_*" type="text_unstemmed_en" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="false" />',
        '<dynamicField name="ts_X3b_und_*" type="text_und" stored="true" indexed="true" multiValued="false" termVectors="true" omitNorms="false" />',
        '<dynamicField name="tm_X3b_und_*" type="text_und" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="false" />',
        '<dynamicField name="tos_X3b_und_*" type="text_und" stored="true" indexed="true" multiValued="false" termVectors="true" omitNorms="true" />',
        '<dynamicField name="tom_X3b_und_*" type="text_und" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="true" />',
        '<dynamicField name="tus_X3b_und_*" type="text_und" stored="true" indexed="true" multiValued="false" termVectors="true" omitNorms="false" />',
        '<dynamicField name="tum_X3b_und_*" type="text_und" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="false" />',
        '<dynamicField name="tus_*" type="text_und" stored="true" indexed="true" multiValued="false" termVectors="true" omitNorms="false" />',
        '<dynamicField name="tum_*" type="text_und" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="false" />',
        '<dynamicField name="ts_X3b_de_*" type="text_de" stored="true" indexed="true" multiValued="false" termVectors="true" omitNorms="false" />',
        '<dynamicField name="tm_X3b_de_*" type="text_de" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="false" />',
        '<dynamicField name="tos_X3b_de_*" type="text_de" stored="true" indexed="true" multiValued="false" termVectors="true" omitNorms="true" />',
        '<dynamicField name="tom_X3b_de_*" type="text_de" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="true" />',
        '<dynamicField name="tus_X3b_de_*" type="text_unstemmed_de" stored="true" indexed="true" multiValued="false" termVectors="true" omitNorms="false" />',
        '<dynamicField name="tum_X3b_de_*" type="text_unstemmed_de" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="false" />',
        '<dynamicField name="spellcheck_und*" type="text_spell_und" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="true" />',
        '<dynamicField name="spellcheck_*" type="text_spell_und" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="true" />',
        '<dynamicField name="sort_X3b_en_*" type="collated_en" stored="false"',
        '<dynamicField name="sort_X3b_de_*" type="collated_de" stored="false"',
        '<dynamicField name="sort_X3b_und_*" type="collated_und" stored="false"',
        '<dynamicField name="sort_*" type="collated_und" stored="false" ',
      ],
      'solrconfig_extra.xml' => [
        '<str name="name">en</str>',
        '<str name="name">de</str>',
      ],
      # phonetic is currently not available vor Solr 6.x.
      #'stopwords_phonetic_en.txt' => [],
      #'protwords_phonetic_en.txt' => [],
      'stopwords_en.txt' => [],
      'synonyms_en.txt' => [
        'drupal, durpal',
      ],
      'protwords_en.txt' => [],
      'accents_en.txt' => [
        '"\u00C4" => "A"'
      ],
      'stopwords_de.txt' => [],
      'synonyms_de.txt' => [
        'drupal, durpal',
      ],
      'protwords_de.txt' => [],
      'accents_de.txt' => [
        ' Not needed if German2 Porter stemmer is used.'
      ],
      'elevate.xml' => [],
      'schema.xml' => [],
      'solrconfig.xml' => [],
      'test.txt' => [
        'hook_search_api_solr_config_files_alter() works'
      ],
    ]]];
    // @codingStandardsIgnoreEnd
  }

}
