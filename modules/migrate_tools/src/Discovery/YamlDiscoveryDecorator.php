<?php

declare(strict_types=1);

namespace Drupal\migrate_tools\Discovery;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Core\Plugin\Discovery\YamlDirectoryDiscovery;

/**
 * Extends YAML directory discovery to allow BC with single-file discovery.
 *
 * @todo Mark plugins from the decorated discovery as deprecated.
 *
 * @todo Remove this in 7.0.0 and use YamlDirectoryDiscovery directly.
 */
class YamlDiscoveryDecorator extends YamlDirectoryDiscovery {

  public function __construct(
    protected DiscoveryInterface $decorated,
    array $directories,
    $file_cache_key_suffix,
    $key = 'id',
  ) {
    parent::__construct($directories, $file_cache_key_suffix, $key);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    return parent::getDefinitions() + $this->decorated->getDefinitions();
  }

  /**
   * Passes through all unknown calls onto the decorated object.
   */
  public function __call($method, $args) {
    return call_user_func_array([$this->decorated, $method], $args);
  }

}
