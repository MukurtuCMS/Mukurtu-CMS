<?php

namespace Drupal\mukurtu_import\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\message\Entity\Message;
use Drupal\message\MessageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns the response for the mukurtu_import.batch_report route.
 */
class BatchReportController extends ControllerBase {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  public function __construct(DateFormatterInterface $date_formatter) {
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('date.formatter'));
  }

  /**
   * Loads the message for the given route parameter.
   *
   * Deliberately not an upcast entity route parameter (no
   * `type: entity:message` in the route) -- Gin theme's breadcrumb
   * preprocessing (includes/breadcrumb.theme's _gin_get_route_entity())
   * treats any route parameter typed as an entity as "the content this
   * admin page is about" and points its "Back to site" link straight at
   * that entity's own canonical page. For a Message entity, that's
   * /message/{id}, a page no one is meant to browse directly (it renders
   * every text delta of the template concatenated, which is why it looked
   * like a duplicated report). Loading manually here keeps that link
   * working normally instead.
   */
  protected function loadMessage(string $message): MessageInterface {
    $entity = Message::load($message);
    if (!$entity instanceof MessageInterface || $entity->bundle() !== 'mukurtu_batch_import_report') {
      throw new NotFoundHttpException();
    }
    return $entity;
  }

  /**
   * Builds the report page for a single batch import, keyed by message ID.
   *
   * The mukurtu_batch_import_report message created at the end of a batch
   * import is the only persisted, ID-addressable record of that batch --
   * see mukurtu_notifications_notify_batch_import_report(), which stores
   * the per-migration breakdown and any error messages on the message's
   * field_import_results field for exactly this purpose.
   */
  public function build(string $message) {
    $message = $this->loadMessage($message);
    $count = $message->hasField('field_number_imported') ? $message->get('field_number_imported')->value : 0;

    $build['summary'] = [
      '#markup' => $this->t('Imported %count items on %date.', [
        '%count' => $count,
        '%date' => $this->dateFormatter->format($message->getCreatedTime()),
      ]),
    ];

    if ($message->hasField('field_import_results') && !$message->get('field_import_results')->isEmpty()) {
      // buildResultsSummary() renders an "Errors" heading as an <h3> when a
      // batch had failures (item_list.html.twig always uses <h3> for
      // #title); this <h2> ensures that isn't a heading-level skip after
      // the page's <h1> title.
      $build['results_heading'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Results'),
      ];
      $build['results'] = $message->get('field_import_results')->view(['label' => 'hidden']);
    }

    return $build;
  }

  /**
   * Title callback for the mukurtu_import.batch_report route.
   */
  public function title(string $message) {
    $message = $this->loadMessage($message);
    return $this->t('Batch import report: @date', [
      '@date' => $this->dateFormatter->format($message->getCreatedTime()),
    ]);
  }

  /**
   * Access callback for the mukurtu_import.batch_report route.
   *
   * Restricted to the mukurtu_batch_import_report bundle specifically, so
   * the {message} route parameter can't be used to view arbitrary message
   * entities (which may contain content not everyone should see) just
   * because the visitor has the generic "access mukurtu import" permission.
   */
  public function access(string $message) {
    $entity = Message::load($message);
    return AccessResult::allowedIf(
      $entity instanceof MessageInterface && $entity->bundle() === 'mukurtu_batch_import_report' && $this->currentUser()->hasPermission('access mukurtu import')
    );
  }

}
