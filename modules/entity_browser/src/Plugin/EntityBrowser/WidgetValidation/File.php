<?php

namespace Drupal\entity_browser\Plugin\EntityBrowser\WidgetValidation;

use Drupal\entity_browser\WidgetValidationBase;
use Drupal\file\Validation\FileValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Validates a file based on passed validators.
 *
 * @EntityBrowserWidgetValidation(
 *   id = "file",
 *   label = @Translation("File validator")
 * )
 */
class File extends WidgetValidationBase {

  /**
   * File validator.
   *
   * @var \Drupal\file\Validation\FileValidatorInterface
   */
  protected FileValidatorInterface $fileValidator;

  /**
   *
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->fileValidator = $container->get('file.validator');
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $entities, array $options = []) {
    $violations = new ConstraintViolationList();

    // We implement the same logic as \Drupal\file\Plugin\Validation\Constraint\FileValidationConstraintValidator
    // here as core does not always write constraints with non-form use cases
    // in mind.
    foreach ($entities as $entity) {
      if (isset($options['validators'])) {
        // Checks that a file meets the criteria specified by the validators.
        if ($violations = $this->fileValidator->validate($entity, $options['validators'])) {
          foreach ($violations as $violation) {
            $violation = new ConstraintViolation($violation->getMessage(), $violation->getMessage(), [], $entity, '', $entity);
            $violations->add($violation);
          }
        }
      }
    }

    return $violations;
  }

}
