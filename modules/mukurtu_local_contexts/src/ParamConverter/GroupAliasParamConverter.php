<?php

namespace Drupal\mukurtu_local_contexts\ParamConverter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\Routing\Route;
use Drupal\Core\ParamConverter\ParamConverterInterface;

/**
 * Converts a URL alias slug to a community or protocol entity.
 *
 * Supports types 'community_alias' and 'protocol_alias' in routing definitions.
 * Falls back to numeric ID if no alias match is found.
 */
class GroupAliasParamConverter implements ParamConverterInterface {

  public function __construct(
    protected AliasManagerInterface $aliasManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $type = $definition['type'];

    if ($type === 'community_alias') {
      $entityType = 'community';
      $aliasPrefix = '/community/';
      $pathPrefix = '/communities/community/';
    }
    else {
      $entityType = 'protocol';
      $aliasPrefix = '/protocol/';
      $pathPrefix = '/protocols/protocol/';
    }

    // Try to resolve by path alias.
    $alias = $aliasPrefix . $value;
    $systemPath = $this->aliasManager->getPathByAlias($alias);

    if ($systemPath !== $alias && str_starts_with($systemPath, $pathPrefix)) {
      $id = substr($systemPath, strlen($pathPrefix));
      if (is_numeric($id)) {
        return $this->entityTypeManager->getStorage($entityType)->load($id);
      }
    }

    // Fall back to numeric ID.
    if (is_numeric($value)) {
      return $this->entityTypeManager->getStorage($entityType)->load($value);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return !empty($definition['type']) && in_array($definition['type'], ['community_alias', 'protocol_alias']);
  }

}
