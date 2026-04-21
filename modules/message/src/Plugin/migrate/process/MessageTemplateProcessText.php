<?php

namespace Drupal\message\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Message template text process plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "d7_message_template_text"
 * )
 */
class MessageTemplateProcessText extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The migration.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface
   */
  protected $migration;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->migration = $migration;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   *
   * Transform the message template texts.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $source = $row->getSource();
    $message_tpl = $this->entityTypeManager->getStorage('message_template')->load($row->getSource()['name']);

    $texts = [];
    if (!empty($message_tpl)) {
      $texts = $message_tpl->getRawText();
    }

    $texts[] = [
      'value' => $source['message_text_value'],
      'format' => $source['message_text_format'],
    ];

    return $texts;
  }

}
