<?php

namespace Drupal\mukurtu_protocol\Plugin\Field\FieldFormatter;

use Drupal\comment\Plugin\Field\FieldFormatter\CommentDefaultFormatter;
use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\mukurtu_protocol\Entity\ProtocolControl;

/**
 * Provides a default comment formatter for Mukurtu.
 *
 * @FieldFormatter(
 *   id = "mukurtu_comment_default",
 *   label = @Translation("Comment list for Mukurtu"),
 *   field_types = {
 *     "comment"
 *   },
 *   quickedit = {
 *     "editor" = "disabled"
 *   }
 * )
 */
class MukurtuCommentDefaultFormatter extends CommentDefaultFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $output = [];

    $field_name = $this->fieldDefinition->getName();
    $entity = $items->getEntity();

    $status = $items->status;

    if ($status != CommentItemInterface::HIDDEN && empty($entity->in_preview) &&
      // Comments are added to the search results and search index by
      // comment_node_update_index() instead of by this formatter, so don't
      // return anything if the view mode is search_index or search_result.
      !in_array($this->viewMode, ['search_result', 'search_index'])) {
      $comment_settings = $this->getFieldSettings();

      // Only attempt to render comments if the entity has visible comments.
      // Unpublished comments are not included in
      // $entity->get($field_name)->comment_count, but unpublished comments
      // should display if the user is an administrator.
      $elements['#cache']['contexts'][] = 'user.permissions';

      if ($this->currentUser->hasPermission('access comments') || ProtocolControl::hasSiteOrProtocolPermission($entity, 'administer comments', $this->currentUser, TRUE)) {
        $output['comments'] = [];

        if ($entity->get($field_name)->comment_count || ProtocolControl::hasSiteOrProtocolPermission($entity, 'administer comments', $this->currentUser, TRUE)) {
          $mode = $comment_settings['default_mode'];
          $comments_per_page = $comment_settings['per_page'];
          $comments = $this->storage->loadThread($entity, $field_name, $mode, $comments_per_page, $this->getSetting('pager_id'));
          if ($comments) {
            $build = $this->viewBuilder->viewMultiple($comments, $this->getSetting('view_mode'));
            $build['pager']['#type'] = 'pager';
            // CommentController::commentPermalink() calculates the page number
            // where a specific comment appears and does a subrequest pointing to
            // that page, we need to pass that subrequest route to our pager to
            // keep the pager working.
            $build['pager']['#route_name'] = $this->routeMatch->getRouteObject();
            $build['pager']['#route_parameters'] = $this->routeMatch->getRawParameters()->all();
            if ($this->getSetting('pager_id')) {
              $build['pager']['#element'] = $this->getSetting('pager_id');
            }
            $output['comments'] += $build;
          }
        }
      }

      // Append comment form if the comments are open and the form is set to
      // display below the entity. Do not show the form for the print view mode.
      if ($status == CommentItemInterface::OPEN && $comment_settings['form_location'] == CommentItemInterface::FORM_BELOW && $this->viewMode != 'print') {
        // Only show the add comment form if the user has permission.
        $elements['#cache']['contexts'][] = 'user.roles';
        if ($this->currentUser->hasPermission('post comments')) {
          $output['comment_form'] = [
            '#lazy_builder' => [
              'comment.lazy_builders:renderForm',
              [
                $entity->getEntityTypeId(),
                $entity->id(),
                $field_name,
                $this->getFieldSetting('comment_type'),
              ],
            ],
            '#create_placeholder' => TRUE,
          ];
        }
      }

      $elements[] = $output + [
        '#comment_type' => $this->getFieldSetting('comment_type'),
        '#comment_display_mode' => $this->getFieldSetting('default_mode'),
        'comments' => [],
        'comment_form' => [],
      ];
    }

    return $elements;
  }

}
