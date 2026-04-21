<?php

namespace Drupal\Tests\search_api_solr_legacy\Kernel;

use Drupal\search_api_solr\Controller\SolrConfigSetController;
use Drupal\search_api_solr_legacy_test\Plugin\SolrConnector\Solr36TestConnector;
use Drupal\Tests\search_api_solr\Kernel\SearchApiSolrTest;

/**
 * Tests index and search capabilities using the Solr search backend.
 *
 * @group search_api_solr_legacy
 */
class SolrLegacyTest extends SearchApiSolrTest {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api_solr_legacy',
    'search_api_solr_legacy_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function installConfigs() {
    parent::installConfigs();

    $this->installConfig([
      'search_api_solr_legacy',
      'search_api_solr_legacy_test',
    ]);

    // Swap the connector.
    Solr36TestConnector::adjustBackendConfig('search_api.server.solr_search_server');
  }

  /**
   * Tests the conversion of Search API queries into Solr queries.
   */
  protected function checkSchemaLanguages() {
    // Solr 3.6 doesn't provide the required REST API.
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
    $this->assertStringNotContainsString('<jmx />', $config_files['solrconfig.xml']);
    $this->assertStringContainsString('solr.luceneMatchVersion=' . $solr_major_version, $config_files['solrcore.properties']);
    $this->assertStringNotContainsString('<statsCache', $config_files['solrconfig.xml']);

    $backend_config['connector_config']['jmx'] = TRUE;
    $backend_config['disabled_field_types'] = [
      'text_foo_en_3_6_0',
      'text_foo_en_4_5_0',
      'text_foo_en_6_0_0',
      'text_de_3_6_0',
      'text_de_4_5_0',
      'text_de_6_0_0',
      'text_de_7_0_0',
    ];
    $backend_config['disabled_caches'] = [
      'cache_document_default_7_0_0',
      'cache_filter_default_7_0_0',
    ];
    $server->setBackendConfig($backend_config);
    $server->save();
    // Reset static caches.
    $solr_configset_controller->setServer($server);

    $config_files = $solr_configset_controller->getConfigFiles();
    $this->assertStringContainsString('<jmx />', $config_files['solrconfig.xml']);
    $this->assertStringContainsString('text_en', $config_files['schema.xml']);
    $this->assertStringNotContainsString('text_foo_en', $config_files['schema.xml']);
    $this->assertStringNotContainsString('text_de', $config_files['schema.xml']);
    $this->assertStringContainsString('httpCaching', $config_files['solrconfig.xml']);
    $this->assertStringContainsString('never304="true"', $config_files['solrconfig.xml']);
    $this->assertStringContainsString('ts_X3b_en_*', $config_files['schema.xml']);
    $this->assertStringNotContainsString('ts_X3b_de_*', $config_files['schema.xml']);

    /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
    $backend = $server->getBackend();
    $this->assertStringContainsString('solr.install.dir', $config_files['solrcore.properties']);
    $this->assertStringContainsString('solr.replication', $config_files['solrcore.properties']);
    $this->assertStringContainsString('"/replication"', $config_files['solrconfig.xml']);
  }

  /**
   * Data provider for testConfigGeneration method.
   */
  public static function configGenerationDataProvider() {
    // @codingStandardsIgnoreStart
    return [[[
      'schema.xml' => [
        # phonetic is currently not available for Solr <= 7.x.
        #'fieldType name="text_phonetic_en" class="solr.TextField"',
        'fieldType name="text_en" class="solr.TextField"',
        'fieldType name="text_de" class="solr.TextField"',
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
      ],
      'solrconfig.xml' => [
        '<str name="name">en</str>',
        '<str name="name">de</str>',
      ],
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
      'test.txt' => [
        'hook_search_api_solr_config_files_alter() works'
      ],
    ]]];
    // @codingStandardsIgnoreEnd
  }

}
