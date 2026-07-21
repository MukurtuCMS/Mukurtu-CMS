<?php

namespace Drupal\mukurtu_local_contexts\ParamConverter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\Routing\Route;

/**
 * Resolves a community/protocol route parameter from its URL alias slug.
 *
 * Lets the Local Contexts directory routes use the same human-readable slug
 * as the entity's own canonical alias (e.g. /community/{name}/local-contexts)
 * instead of the raw entity ID, falling back to the ID when the value is
 * numeric (e.g. an entity that doesn't have an alias yet).
 */
class GroupAliasParamConverter implements ParamConverterInterface {

  /**
   * Maps each supported parameter type to its entity type and alias prefix.
   */
  const TYPE_MAP = [
    'community_alias' => [
      'entity_type' => 'community',
      'alias_prefix' => '/community/',
      'canonical_prefix' => '/communities/community/',
    ],
    'protocol_alias' => [
      'entity_type' => 'protocol',
      'alias_prefix' => '/protocol/',
      'canonical_prefix' => '/protocols/protocol/',
    ],
  ];

  public function __construct(
    protected AliasManagerInterface $aliasManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $info = static::TYPE_MAP[$definition['type']] ?? NULL;
    if (!$info) {
      return NULL;
    }

    if (is_numeric($value)) {
      return $this->entityTypeManager->getStorage($info['entity_type'])->load($value);
    }

    $system_path = $this->aliasManager->getPathByAlias($info['alias_prefix'] . $value);
    if (!str_starts_with($system_path, $info['canonical_prefix'])) {
      return NULL;
    }

    $id = substr($system_path, strlen($info['canonical_prefix']));
    if (!is_numeric($id)) {
      return NULL;
    }

    return $this->entityTypeManager->getStorage($info['entity_type'])->load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return !empty($definition['type']) && isset(static::TYPE_MAP[$definition['type']]);
  }

}
