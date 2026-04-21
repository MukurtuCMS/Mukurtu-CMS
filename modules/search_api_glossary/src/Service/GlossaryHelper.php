<?php

namespace Drupal\search_api_glossary\Service;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Search Api Glossary AZ Helper class.
 *
 * @package Drupal\search_api_glossary
 */
class GlossaryHelper {

  /**
   * Config.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * GlossaryHelper Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   An instance of ConfigFactory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler service.
   */
  public function __construct(ConfigFactory $config,
                              ModuleHandlerInterface $module_handler) {
    $this->config = $config->get('search_api_glossary.settings');
    $this->moduleHandler = $module_handler;
  }

  /**
   * Glossary Getter.
   *
   * Method to determine what Glossary value to set.
   *
   * @param string $source_value
   *   Field value to be used for Glossary.
   * @param array $glossary_az_grouping
   *   What groupings are enabled.
   *
   * @return string
   *   Either a Group Name or First letter of the item.
   */
  public function glossaryGetter($source_value, array $glossary_az_grouping) {
    // Trim it, then get first letter, then uppercase it.
    $first_letter = mb_strtoupper(mb_substr(trim($source_value), 0, 1));

    // Allow other modules to hook in and alter the first letter.
    $this->moduleHandler->alter('search_api_glossary_source', $first_letter);

    // Finally check groupings and alter the first letter.
    return $this->glossaryGroupName($first_letter, array_values($glossary_az_grouping));
  }

  /**
   * Helper for Alpha Numeric Keys.
   *
   * Determines what Group value to use.
   *
   * @param string $first_letter
   *   First Letter for the Glossary.
   * @param array $glossary_az_grouping
   *   What groupings are enabled.
   *
   * @return string
   *   Processed First Letter if using groups.
   */
  public function glossaryGroupName($first_letter, array $glossary_az_grouping) {
    $group_prefix = $this->glossaryGetGroupNamePrefix();

    // Do we have Alpha grouping?
    if (in_array('grouping_az', $glossary_az_grouping, TRUE) && $this->isAlpha($first_letter) == TRUE) {
      // To get AZ equivalent in native language, change the settings YAML.
      $first_letter = $group_prefix['alpha'];
    }

    // Do we have Numeric grouping?
    elseif (in_array('grouping_09', $glossary_az_grouping, TRUE) && $this->isNumeric($first_letter) == TRUE) {
      // To get 0-9 equivalent in native language, change the settings YAML.
      $first_letter = $group_prefix['numeric'];
    }

    // Catch non alpha numeric.
    // Do we have Non Alpha Numeric grouping?
    elseif (in_array('grouping_other', $glossary_az_grouping, TRUE) && $this->isSpecial($first_letter) == TRUE) {
      // To get # equivalent in native language, change the settings YAML.
      $first_letter = $group_prefix['special'];
    }

    // TODO Maybe allow a final alter as the easy way to change groups?
    return $first_letter;
  }

  /**
   * Detect Alpha.
   *
   * @param string $first_letter
   *   First Letter for the Glossary.
   *
   * @return bool
   *   is it Alpha?
   */
  public function isAlpha($first_letter) {
    // Is it Alpha?
    // See http://php.net/manual/en/regexp.reference.unicode.php
    if (preg_match('/^\p{L}+$/u', $first_letter)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Detect Numeric.
   *
   * @param string $first_letter
   *   First Letter for the Glossary.
   *
   * @return bool
   *   is it Numeric?
   */
  public function isNumeric($first_letter) {
    // Is it a number?
    // See http://php.net/manual/en/regexp.reference.unicode.php
    if (preg_match('/^\p{N}+$/u', $first_letter)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Detect non Alpha Numeric.
   *
   * @param string $first_letter
   *   First Letter for the Glossary.
   *
   * @return bool
   *   is it non Alpha Numeric?
   */
  public function isSpecial($first_letter) {
    if ($this->isAlpha($first_letter) == FALSE &&
      $this->isNumeric($first_letter) == FALSE) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Getter for Group Name prefixes.
   *
   * @return array
   *   Configuration Array.
   */
  public function glossaryGetGroupNamePrefix() {
    return $this->config->get('group_prefix');
  }

}
