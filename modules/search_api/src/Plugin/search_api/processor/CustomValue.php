<?php

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Token;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\search_api\processor\Property\CustomValueProperty;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Allows adding custom tokenized text values to the index.
 */
#[SearchApiProcessor(
  id: 'custom_value',
  label: new TranslatableMarkup('Custom value'),
  description: new TranslatableMarkup('Allows adding custom tokenized text values to the index.'),
  stages: [
    'add_properties' => 0,
  ],
  locked: TRUE,
  hidden: TRUE,
)]
class CustomValue extends ProcessorPluginBase {

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token|null
   */
  protected $token;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->setToken($container->get('token'));
    return $processor;
  }

  /**
   * Retrieves the token service.
   *
   * @return \Drupal\Core\Utility\Token
   *   The token service.
   */
  public function getToken(): Token {
    return $this->token ?: \Drupal::token();
  }

  /**
   * Sets the token service.
   *
   * @param \Drupal\Core\Utility\Token $token
   *   The new token service.
   *
   * @return $this
   */
  public function setToken(Token $token): self {
    $this->token = $token;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Custom value'),
        'description' => $this->t('Index a custom value with replacement tokens.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['custom_value'] = new CustomValueProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    // Get all of the "custom_value" fields on this item.
    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), NULL, 'custom_value');
    // If the indexed item is an entity, we can pass that as data to the token
    // service. Otherwise, only global tokens are available.
    $entity = $item->getOriginalObject()->getValue();
    if ($entity instanceof EntityInterface) {
      $data = [$entity->getEntityTypeId() => $entity];
    }
    else {
      $data = [];
    }

    $token = $this->getToken();
    foreach ($fields as $field) {
      $config = $field->getConfiguration();
      if (empty($config['value'])) {
        continue;
      }
      // Check if there are any tokens to replace.
      $field_value = $config['value'];
      if (preg_match_all('/\[[-\w]++(?::[-\w]++)++]/', $field_value, $matches)) {
        $field_value = $token->replacePlain($field_value, $data);
        // Make sure there are no left-over tokens.
        $field_value = str_replace($matches[0], '', $field_value);
        $field_value = trim($field_value);
      }
      if ($field_value !== '') {
        $field->addValue($field_value);
      }
    }
  }

}
