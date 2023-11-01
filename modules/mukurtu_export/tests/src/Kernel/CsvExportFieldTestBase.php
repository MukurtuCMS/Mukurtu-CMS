<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\mukurtu_export\Entity\CsvExporter;
use Drupal\mukurtu_export\EventSubscriber\CsvEntityFieldExportEventSubscriber;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;

class CsvExportFieldTestBase extends ProtocolAwareEntityTestBase {

  /**
   * @var \Drupal\mukurtu_protocol\Entity\Community
   */
  protected $community;

  /**
   * @var \Drupal\mukurtu_protocol\Entity\Protocol
   */
  protected $protocol;

  protected $context;

  /**
   * @var \Drupal\mukurtu_export\EventSubscriber\CsvEntityFieldExportEventSubscriber
   */
  protected $fieldExporter;

  /**
   * @var \Drupal\mukurtu_export\Event\EntityFieldExportEvent
   */
  protected $event;

  /**
   * @var \Drupal\mukurtu_export\Entity\CsvExporter
   */
  protected $export_config;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'mukurtu_export',
  ];

  protected function setUp(): void {
    parent::setUp();

    $community = Community::create([
      'name' => 'Community',
    ]);
    $community->save();
    $this->community = $community;
    $this->community->addMember($this->currentUser);

    $protocol = Protocol::create([
      'name' => "Protocol",
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'open',
    ]);
    $protocol->save();
    $protocol->addMember($this->currentUser, ['protocol_steward']);
    $this->protocol = $protocol;

    $this->export_config = CsvExporter::create([
      'id' => 'test_csv_exporter',
      'label' => 'Test CSV Exporter',
      'entity_fields_export_list'  => [
        'node__protocol_aware_content' => [
          'title' => 'Title',
        ]
      ],
    ]);
    $this->export_config->save();

    $this->context = [
      'results' => [
        'config_id' => $this->export_config->id(),
      ]
    ];

    $this->fieldExporter = new CsvEntityFieldExportEventSubscriber(\Drupal::messenger(), \Drupal::entityTypeManager());
  }

}
