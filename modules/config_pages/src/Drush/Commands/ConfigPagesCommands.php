<?php

namespace Drupal\config_pages\Drush\Commands;

use Drupal\config_pages\Entity\ConfigPages;
use Drupal\config_pages\Entity\ConfigPagesType;
use Drush\Commands\DrushCommands;

/**
 * A Drush command file.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml.
 */
class ConfigPagesCommands extends DrushCommands {

  /**
   * Set a value for the field of Config Pages.
   *
   * @param string $bundle
   *   The type of config page "/admin/structure/config_pages/types".
   * @param string $field_name
   *   The name of field.
   * @param string $value
   *   The value for the field.
   * @param null $context
   *   ConfigPage context.
   * @param array $options
   *   An associative array of options whose values
   *   come from cli, aliases, config, etc.
   *
   * @option append
   *   Append to an existing value.
   * @usage drush cpsfv bundle field_name value
   *   Set new value for field_name.
   * @usage drush cpsfv bundle field_name value --append
   *   Append a value to existing string.
   * @validate-module-enabled config_pages
   * @command config:pages-set-field-value
   * @aliases cpsfv,config-pages-set-field-value
   */
  public function pagesSetFieldValue($bundle, $field_name, $value, $context = NULL, array $options = ['append' => NULL]) {
    try {
      $config_page = config_pages_config($bundle, $context);

      if (empty($config_page)) {
        $type = ConfigPagesType::load($bundle);
        $config_page = ConfigPages::create([
          'type' => $bundle,
          'label' => $type->label(),
          'context' => $type->getContextData(),
        ]);
        $config_page->save();
      }

      $append = $options['append'];
      if (isset($append)) {
        $value = $config_page->get($field_name)->getString() . $value;
      }

      $config_page->set($field_name, str_replace('\n', PHP_EOL, $value));
      $config_page->save();

      $this->output()->writeln('Saved new value for ' . $field_name . ' field.');
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
    }
  }

  /**
   * Get a value for the field of Config Pages.
   *
   * @param string $bundle
   *   The type of config page "/admin/structure/config_pages/types".
   * @param string $field_name
   *   The name of field.
   * @param null $context
   *   Context.
   *
   * @validate-module-enabled config_pages
   *
   * @command config:pages-get-field-value
   * @aliases cpgfv,config-pages-get-field-value
   */
  public function pagesGetFieldValue($bundle, $field_name, $context = NULL) {
    try {
      $config_page = config_pages_config($bundle, $context);

      if (!empty($config_page)) {
        $this->output()->writeln($config_page->get($field_name)->value);
      }
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
    }
  }

}
