<?php

namespace Drupal\mukurtu_protocol\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\mukurtu_protocol\Entity\ProtocolControlInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ProtocolControlController.
 *
 *  Returns responses for Protocol control routes.
 */
class ProtocolControlController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * Displays a Protocol control revision.
   *
   * @param int $protocol_control_revision
   *   The Protocol control revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($protocol_control_revision) {
    $protocol_control = $this->entityTypeManager()->getStorage('protocol_control')
      ->loadRevision($protocol_control_revision);
    $view_builder = $this->entityTypeManager()->getViewBuilder('protocol_control');

    return $view_builder->view($protocol_control);
  }

  /**
   * Page title callback for a Protocol control revision.
   *
   * @param int $protocol_control_revision
   *   The Protocol control revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($protocol_control_revision) {
    $protocol_control = $this->entityTypeManager()->getStorage('protocol_control')
      ->loadRevision($protocol_control_revision);
    return $this->t('Revision of %title from %date', [
      '%title' => $protocol_control->label(),
      '%date' => $this->dateFormatter->format($protocol_control->getRevisionCreationTime()),
    ]);
  }

  /**
   * Generates an overview table of older revisions of a Protocol control.
   *
   * @param \Drupal\mukurtu_protocol\Entity\ProtocolControlInterface $protocol_control
   *   A Protocol control object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(ProtocolControlInterface $protocol_control) {
    $account = $this->currentUser();
    $protocol_control_storage = $this->entityTypeManager()->getStorage('protocol_control');

    $build['#title'] = $this->t('Revisions for %title', ['%title' => $protocol_control->label()]);

    $header = [$this->t('Revision'), $this->t('Operations')];
    $revert_permission = (($account->hasPermission("revert all protocol control revisions") || $account->hasPermission('administer protocol control entities')));
    $delete_permission = (($account->hasPermission("delete all protocol control revisions") || $account->hasPermission('administer protocol control entities')));

    $rows = [];

    $vids = $protocol_control_storage->revisionIds($protocol_control);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\mukurtu_protocol\ProtocolControlInterface $revision */
      $revision = $protocol_control_storage->loadRevision($vid);
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = $this->dateFormatter->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $protocol_control->getRevisionId()) {
          $link = $this->l($date, new Url('entity.protocol_control.revision', [
            'protocol_control' => $protocol_control->id(),
            'protocol_control_revision' => $vid,
          ]));
        }
        else {
          $link = $protocol_control->link($date);
        }

        $row = [];
        $column = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
            '#context' => [
              'date' => $link,
              'username' => $this->renderer->renderPlain($username),
              'message' => [
                '#markup' => $revision->getRevisionLogMessage(),
                '#allowed_tags' => Xss::getHtmlTagList(),
              ],
            ],
          ],
        ];
        $row[] = $column;

        if ($latest_revision) {
          $row[] = [
            'data' => [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
            ],
          ];
          foreach ($row as &$current) {
            $current['class'] = ['revision-current'];
          }
          $latest_revision = FALSE;
        }
        else {
          $links = [];
          if ($revert_permission) {
            $links['revert'] = [
              'title' => $this->t('Revert'),
              'url' => Url::fromRoute('entity.protocol_control.revision_revert', [
                'protocol_control' => $protocol_control->id(),
                'protocol_control_revision' => $vid,
              ]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.protocol_control.revision_delete', [
                'protocol_control' => $protocol_control->id(),
                'protocol_control_revision' => $vid,
              ]),
            ];
          }

          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];
        }

        $rows[] = $row;
    }

    $build['protocol_control_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
