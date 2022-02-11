<?php

namespace Drupal\mukurtu_protocol\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\mukurtu_protocol\Entity\ProtocolInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ProtocolController.
 *
 *  Returns responses for Protocol routes.
 */
class ProtocolController extends ControllerBase implements ContainerInjectionInterface {

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
   * Displays a Protocol revision.
   *
   * @param int $protocol_revision
   *   The Protocol revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($protocol_revision) {
    $protocol = $this->entityTypeManager()->getStorage('protocol')
      ->loadRevision($protocol_revision);
    $view_builder = $this->entityTypeManager()->getViewBuilder('protocol');

    return $view_builder->view($protocol);
  }

  /**
   * Page title callback for a Protocol revision.
   *
   * @param int $protocol_revision
   *   The Protocol revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($protocol_revision) {
    $protocol = $this->entityTypeManager()->getStorage('protocol')
      ->loadRevision($protocol_revision);
    return $this->t('Revision of %title from %date', [
      '%title' => $protocol->label(),
      '%date' => $this->dateFormatter->format($protocol->getRevisionCreationTime()),
    ]);
  }

  /**
   * Generates an overview table of older revisions of a Protocol.
   *
   * @param \Drupal\mukurtu_protocol\Entity\ProtocolInterface $protocol
   *   A Protocol object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(ProtocolInterface $protocol) {
    $account = $this->currentUser();
    $protocol_storage = $this->entityTypeManager()->getStorage('protocol');

    $langcode = $protocol->language()->getId();
    $langname = $protocol->language()->getName();
    $languages = $protocol->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $protocol->label()]) : $this->t('Revisions for %title', ['%title' => $protocol->label()]);

    $header = [$this->t('Revision'), $this->t('Operations')];
    $revert_permission = (($account->hasPermission("revert all protocol revisions") || $account->hasPermission('administer protocol entities')));
    $delete_permission = (($account->hasPermission("delete all protocol revisions") || $account->hasPermission('administer protocol entities')));

    $rows = [];

    $vids = $protocol_storage->revisionIds($protocol);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\mukurtu_protocol\ProtocolInterface $revision */
      $revision = $protocol_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = $this->dateFormatter->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $protocol->getRevisionId()) {
          $link = $this->l($date, new Url('entity.protocol.revision', [
            'protocol' => $protocol->id(),
            'protocol_revision' => $vid,
          ]));
        }
        else {
          $link = $protocol->link($date);
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
              'url' => $has_translations ?
              Url::fromRoute('entity.protocol.translation_revert', [
                'protocol' => $protocol->id(),
                'protocol_revision' => $vid,
                'langcode' => $langcode,
              ]) :
              Url::fromRoute('entity.protocol.revision_revert', [
                'protocol' => $protocol->id(),
                'protocol_revision' => $vid,
              ]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.protocol.revision_delete', [
                'protocol' => $protocol->id(),
                'protocol_revision' => $vid,
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
    }

    $build['protocol_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
