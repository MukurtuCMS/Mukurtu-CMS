<?php

namespace Drupal\config_pages\Plugin\ConfigPagesContext;

use Drupal\config_pages\Attribute\ConfigPagesContext;
use Drupal\config_pages\ConfigPagesContextBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a language config pages context.
 *
 * @ConfigPagesContext(
 *   id = "language",
 *   label = @Translation("Language"),
 * )
 */
#[ConfigPagesContext(
  id: "language",
  label: new TranslatableMarkup("Language"),
)]
class Language extends ConfigPagesContextBase {

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  private $languageManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LanguageManagerInterface $language_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager')
    );
  }

  /**
   * Get the value of the context.
   *
   * @return mixed
   *   Return value of the context.
   */
  public function getValue() {
    $lang = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT);
    return $lang->getId();
  }

  /**
   * Get the label of the context.
   *
   * @return string
   *   Return the label of the context.
   */
  public function getLabel() {
    return $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getName();
  }

  /**
   * Get array of available links to switch on given context.
   *
   * @return array
   *   Return array of available links to switch on given context.
   */
  public function getLinks() {
    $links = [];
    $value = $this->getValue();
    $languages = $this->languageManager->getLanguages();
    foreach ($languages as $lang) {
      $links[] = [
        'title' => $lang->getName(),
        'href' => Url::fromRoute('<current>', [], ['language' => $lang]),
        'selected' => ($value == $lang->getId()) ? TRUE : FALSE,
        'value' => $lang->getId(),
      ];
    }
    return $links;
  }

}
