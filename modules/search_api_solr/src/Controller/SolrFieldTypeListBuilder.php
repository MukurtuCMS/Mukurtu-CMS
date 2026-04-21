<?php

namespace Drupal\search_api_solr\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\SolrFieldTypeInterface;

/**
 * Provides a listing of SolrFieldType.
 */
class SolrFieldTypeListBuilder extends AbstractSolrEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'label' => $this->t('Solr Field Type'),
      'minimum_solr_version' => $this->t('Minimum Solr Version'),
      'managed_schema' => $this->t('Managed Schema Required'),
      'langcode' => $this->t('Language'),
      'domains' => $this->t('Domains'),
      'id' => $this->t('Machine name'),
      'enabled' => $this->t('Enabled'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $_field_type) {
    /** @var \Drupal\search_api_solr\SolrFieldTypeInterface $_field_type */
    $domains = $_field_type->getDomains();
    if (empty($domains)) {
      $domains = ['generic'];
    }

    $enabled_label = $_field_type->isDisabledOnServer() ? $this->t('Disabled') : $this->t('Enabled');
    $enabled_icon = [
      '#theme' => 'image',
      '#uri' => !$_field_type->isDisabledOnServer() ? 'core/misc/icons/73b355/check.svg' : 'core/misc/icons/e32700/error.svg',
      '#width' => 18,
      '#height' => 18,
      '#alt' => $enabled_label,
      '#title' => $enabled_label,
    ];

    $row = [
      'label' => $_field_type->label(),
      'minimum_solr_version' => $_field_type->getMinimumSolrVersion(),
      // @todo format
      'managed_schema' => $_field_type->requiresManagedSchema(),
      // @todo format
      'langcode' => $_field_type->getFieldTypeLanguageCode(),
      // @todo format
      'domains' => implode(', ', $domains),
      'id' => $_field_type->id(),
      'enabled' => [
        'data' => $enabled_icon,
        'class' => ['checkbox'],
      ],
    ];
    return $row + parent::buildRow($_field_type);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function load(): array {
    static $entities;

    $active_languages = array_keys(\Drupal::languageManager()->getLanguages());
    // Ignore region and variant of the locale string the language manager
    // returns as we provide language fallbacks. For example, 'de' should be
    // used for 'de-at' if there's no dedicated 'de-at' field type.
    array_walk($active_languages, function (&$value) {
      [$value] = explode('-', $value);
    });
    $active_languages[] = LanguageInterface::LANGCODE_NOT_SPECIFIED;

    if (!$entities || $this->reset) {
      $solr_version = '9999.0.0';
      $operator = '>=';
      $domain = 'generic';
      $warning = FALSE;
      $disabled_field_types = [];
      try {
        /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
        $backend = $this->getBackend();
        $disabled_field_types = $this->getDisabledEntities();
        $domain = $backend->getDomain();
        $solr_version = $backend->getSolrConnector()->getSolrVersion();
        if (version_compare($solr_version, '0.0.0', '==')) {
          $solr_version = '9999.0.0';
          throw new SearchApiSolrException();
        }
      }
      catch (SearchApiSolrException $e) {
        $operator = '<=';
        $warning = TRUE;
      }

      // We need the whole list to work on.
      $this->limit = FALSE;
      $entity_ids = $this->getEntityIds();
      /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $storage */
      $storage = $this->getStorage();
      /** @var \Drupal\search_api_solr\SolrFieldTypeInterface[] $entities */
      $entities = $storage->loadMultipleOverrideFree($entity_ids);

      // We filter those field types that are relevant for the current server.
      // There are multiple entities having the same field_type.name but
      // different values for minimum_solr_version and domains.
      $selection = [];
      foreach ($entities as $key => $solr_field_type) {
        $entities[$key]->setDisabledOnServer(in_array($solr_field_type->id(), $disabled_field_types));
        $version = $solr_field_type->getMinimumSolrVersion();
        $domains = $solr_field_type->getDomains();
        [$language] = explode('-', $solr_field_type->getFieldTypeLanguageCode());
        if (
          $solr_field_type->requiresManagedSchema() != $this->getBackend()->isManagedSchema() ||
          version_compare($version, $solr_version, '>') ||
          !in_array($language, $active_languages) ||
          (!in_array($domain, $domains) && !in_array('generic', $domains))
        ) {
          unset($entities[$key]);
        }
        else {
          $name = $solr_field_type->getFieldTypeName();
          if (isset($selection[$name])) {
            // The more specific domain has precedence over a newer version.
            if (
              // Current selection domain is 'generic' but something more
              // specific is found.
              ('generic' !== $domain && 'generic' === $selection[$name]['domain'] && in_array($domain, $domains)) ||
              // A newer version of the current selection domain is found.
              (version_compare($version, $selection[$name]['version'], $operator) && in_array($selection[$name]['domain'], $domains))
            ) {
              $this->mergeFieldTypes($entities[$key], $entities[$selection[$name]['key']]);
              unset($entities[$selection[$name]['key']]);
              $selection[$name] = [
                'version' => $version,
                'key' => $key,
                'domain' => in_array($domain, $domains) ? $domain : 'generic',
              ];
            }
            else {
              $this->mergeFieldTypes($entities[$selection[$name]['key']], $entities[$key]);
              unset($entities[$key]);
            }
          }
          else {
            $selection[$name] = [
              'version' => $version,
              'key' => $key,
              'domain' => in_array($domain, $domains) ? $domain : 'generic',
            ];
          }
        }
      }

      if ($warning) {
        $this->assumedMinimumVersion = array_reduce($selection, function ($version, $item) {
          if (version_compare($item['version'], $version, '<')) {
            return $item['version'];
          }
          return $version;
        }, $solr_version);

        \Drupal::messenger()->addWarning(
          $this->t(
            'Unable to reach the Solr server (yet). Therefore the lowest supported Solr version %version is assumed. Once the connection works and the real Solr version could be detected it might be necessary to deploy an adjusted config to the server to get the best search results. If the server does not start using the downloadable config, you should edit the server and manually set the Solr version override temporarily that fits your server best and download the config again. But it is recommended to remove this override once the server is running.',
            ['%version' => $this->assumedMinimumVersion])
        );
      }

      // Sort the entities using the entity class's sort() method.
      // See \Drupal\Core\Config\Entity\ConfigEntityBase::sort().
      uasort($entities, [$this->entityType->getClass(), 'sort']);
      $this->reset = FALSE;
    }

    return $entities;
  }

  /**
   * Returns a list of all disabled request handlers for current server.
   *
   * @return array
   *   A list of all disabled request handlers for current server.
   */
  protected function getDisabledEntities(): array {
    $backend = $this->getBackend();
    return $backend->getDisabledFieldTypes();
  }

  /**
   * Merge two Solr field type entities.
   *
   * @param \Drupal\search_api_solr\SolrFieldTypeInterface $target
   *   The target Solr field type entity.
   * @param \Drupal\search_api_solr\SolrFieldTypeInterface $source
   *   The source Solr field type entity.
   */
  protected function mergeFieldTypes(SolrFieldTypeInterface $target, SolrFieldTypeInterface $source) {
    if (empty($target->getCollatedFieldType()) && !empty($source->getCollatedFieldType())) {
      $target->setCollatedFieldType($source->getCollatedFieldType());
    }
    if (empty($target->getSpellcheckFieldType()) && !empty($source->getSpellcheckFieldType())) {
      $target->setSpellcheckFieldType($source->getSpellcheckFieldType());
    }
    if (empty($target->getUnstemmedFieldType()) && !empty($source->getUnstemmedFieldType())) {
      $target->setUnstemmedFieldType($source->getUnstemmedFieldType());
    }
    if (empty($target->getSolrConfigs()) && !empty($source->getSolrConfigs())) {
      $target->setSolrConfigs($source->getSolrConfigs());
    }
    if (empty($target->getTextFiles()) && !empty($source->getTextFiles())) {
      $target->setTextFiles($source->getTextFiles());
    }
  }

  /**
   * Returns the formatted XML for schema_extra_types.xml.
   *
   * @return string
   *   The XML snippet.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getSchemaExtraTypesXml() {
    $xml = '';
    /** @var \Drupal\search_api_solr\SolrFieldTypeInterface $solr_field_type */
    foreach ($this->getEnabledEntities() as $solr_field_type) {
      $xml .= $solr_field_type->getAsXml();
      $xml .= $solr_field_type->getSpellcheckFieldTypeAsXml();
      $xml .= $solr_field_type->getCollatedFieldTypeAsXml();
      $xml .= $solr_field_type->getUnstemmedFieldTypeAsXml();
    }
    return $xml;
  }

  /**
   * Returns the formatted XML for solrconfig_extra.xml.
   *
   * @param int|null $solr_major_version
   *   The Solr major version.
   *
   * @return string
   *   The XML snippet.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getSchemaExtraFieldsXml(?int $solr_major_version = NULL) {
    $xml = '';
    /** @var \Drupal\search_api_solr\SolrFieldTypeInterface $solr_field_type */
    foreach ($this->getEnabledEntities() as $solr_field_type) {
      foreach ($solr_field_type->getStaticFields() as $static_field) {
        $xml .= '<field ';
        foreach ($static_field as $attribute => $value) {
          /* @noinspection NestedTernaryOperatorInspection */
          $xml .= $attribute . '="' . (is_bool($value) ? ($value ? 'true' : 'false') : $value) . '" ';
        }
        $xml .= "/>\n";
      }
      foreach ($solr_field_type->getDynamicFields($solr_major_version) as $dynamic_field) {
        $xml .= '<dynamicField ';
        foreach ($dynamic_field as $attribute => $value) {
          /* @noinspection NestedTernaryOperatorInspection */
          $xml .= $attribute . '="' . (is_bool($value) ? ($value ? 'true' : 'false') : $value) . '" ';
        }
        $xml .= "/>\n";
      }

      foreach ($solr_field_type->getCopyFields() as $copy_field) {
        $xml .= '<copyField ';
        foreach ($copy_field as $attribute => $value) {
          /* @noinspection NestedTernaryOperatorInspection */
          $xml .= $attribute . '="' . (is_bool($value) ? ($value ? 'true' : 'false') : $value) . '" ';
        }
        $xml .= "/>\n";
      }
    }
    return $xml;
  }

  /**
   * Returns the formatted XML for solrconfig_extra.xml.
   *
   * @return string
   *   The XML snippet.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getSolrconfigExtraXml() {
    $search_components = [];
    /** @var \Drupal\search_api_solr\SolrFieldTypeInterface $solr_field_type */
    foreach ($this->getEnabledEntities() as $solr_field_type) {
      $xml = $solr_field_type->getSolrConfigsAsXml();
      if (preg_match_all('@(<searchComponent name="[^"]+"[^>]*?>)(.*?)</searchComponent>@sm', $xml, $matches)) {
        foreach ($matches[1] as $key => $search_component) {
          $search_components[$search_component][] = $matches[2][$key];
        }
      }
    }

    $xml = '';
    foreach ($search_components as $search_component => $details) {
      $xml .= $search_component;
      foreach ($details as $detail) {
        $xml .= $detail;
      }
      $xml .= "</searchComponent>\n";
    }

    return $xml;
  }

}
