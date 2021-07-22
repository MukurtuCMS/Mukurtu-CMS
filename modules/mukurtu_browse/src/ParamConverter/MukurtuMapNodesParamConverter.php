<?php

namespace Drupal\mukurtu_browse\ParamConverter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\Routing\Route;
use Drupal\Core\ParamConverter\ParamConverterInterface;

class MukurtuMapNodesParamConverter implements ParamConverterInterface {
  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public function convert($value, $definition, $name, array $defaults) {
    $nids = explode(',', $value);

    if (!empty($nids)) {
      return $this->entityTypeManager->getStorage('node')
        ->loadMultiple($nids);
    }
    return [];
  }

  public function applies($definition, $name, Route $route) {
    return !empty($definition['type']) && $definition['type'] == 'nodes';
  }
}
