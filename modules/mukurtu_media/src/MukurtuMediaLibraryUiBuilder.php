<?php

namespace Drupal\mukurtu_media;

use Drupal\Core\Url;
use Drupal\media_library\MediaLibraryState;
use Drupal\media_library\MediaLibraryUiBuilder;

/**
 * Extends the core media library UI builder.
 *
 * - Defaults to the table (list) display instead of core's grid default.
 * - Adds an "All media" tab that shows every allowed media type combined,
 *   with a Media type exposed filter and no add form.
 * - Opens the "All media" tab by default.
 *
 * The "All media" flag is stored in opener_parameters so it is included in
 * the HMAC hash and survives every AJAX round-trip, including exposed-filter
 * submits whose action URL is built from the hash-signed state.
 */
class MukurtuMediaLibraryUiBuilder extends MediaLibraryUiBuilder {

  /**
   * Returns TRUE when the state is in "All media" mode.
   */
  protected function isAllMediaMode(MediaLibraryState $state): bool {
    return !empty($state->getOpenerParameters()['mukurtu_show_all']);
  }

  /**
   * Returns a new hash-signed state without mukurtu_show_all in opener_parameters.
   */
  protected function stateWithoutAllMedia(MediaLibraryState $state): MediaLibraryState {
    $opener_params = $state->getOpenerParameters();
    unset($opener_params['mukurtu_show_all']);
    return MediaLibraryState::create(
      $state->getOpenerId(),
      $state->getAllowedTypeIds(),
      $state->getSelectedTypeId(),
      $state->getAvailableSlots(),
      $opener_params
    );
  }

  /**
   * Returns a new hash-signed state with mukurtu_show_all in opener_parameters.
   */
  protected function stateWithAllMedia(MediaLibraryState $state): MediaLibraryState {
    $opener_params = $state->getOpenerParameters();
    $opener_params['mukurtu_show_all'] = '1';
    return MediaLibraryState::create(
      $state->getOpenerId(),
      $state->getAllowedTypeIds(),
      $state->getSelectedTypeId(),
      $state->getAvailableSlots(),
      $opener_params
    );
  }

  /**
   * {@inheritdoc}
   *
   * On the initial modal open (no media_library_content param), upgrade the
   * state so "All media" mode is the default. The new state is hash-signed, so
   * the exposed-filter form action URL will include the flag and filter submits
   * will correctly stay in "All media" mode.
   */
  public function buildUi(?MediaLibraryState $state = NULL) {
    if (!$state) {
      $state = MediaLibraryState::fromRequest($this->request);
    }
    if (!$state->get('media_library_content') && !$this->isAllMediaMode($state)) {
      $state = $this->stateWithAllMedia($state);
    }
    return parent::buildUi($state);
  }

  /**
   * {@inheritdoc}
   *
   * Prepends an "All media" tab that shows all allowed media types combined.
   */
  protected function buildMediaTypeMenu(MediaLibraryState $state) {
    // Pass a clean state (without mukurtu_show_all) to the parent so the
    // per-type tab URLs do not carry the "All media" flag. Per-type tabs
    // should navigate to type-specific views, not stay in "All media" mode.
    $clean_state = $this->stateWithoutAllMedia($state);
    $menu = parent::buildMediaTypeMenu($clean_state);

    $allowed_type_ids = $state->getAllowedTypeIds();
    if (count($allowed_type_ids) <= 1) {
      return $menu;
    }

    $is_all_active = $this->isAllMediaMode($state);

    // When "All media" is active, strip the active class the parent placed on
    // the per-type link that matches the state's selected_type_id.
    if ($is_all_active && !empty($menu['#links'])) {
      $key = 'media-library-menu-' . $state->getSelectedTypeId();
      if (isset($menu['#links'][$key]['attributes']['class'])) {
        $menu['#links'][$key]['attributes']['class'] = array_values(
          array_filter($menu['#links'][$key]['attributes']['class'], fn($c) => $c !== 'active')
        );
      }
    }

    // Build the "All media" tab. Anchor it to the first allowed type to satisfy
    // state validation; store the flag in opener_parameters (hash-signed) so it
    // survives filter-submit AJAX round-trips.
    $first_type_id = reset($allowed_type_ids);
    $all_state = $this->stateWithAllMedia(
      MediaLibraryState::create(
        $state->getOpenerId(),
        $state->getAllowedTypeIds(),
        $first_type_id,
        $state->getAvailableSlots(),
        $state->getOpenerParameters()
      )
    );
    $all_state->set('media_library_content', 1);

    $label = $this->t('All media');
    $display_title = $is_all_active
      ? $this->t('<span class="visually-hidden">Show </span>@title<span class="visually-hidden"> media</span><span class="active-tab visually-hidden"> (selected)</span>', ['@title' => $label])
      : $this->t('<span class="visually-hidden">Show </span>@title<span class="visually-hidden"> media</span>', ['@title' => $label]);

    $all_link = [
      'title' => $display_title,
      'url' => Url::fromRoute('media_library.ui', [], ['query' => $all_state->all()]),
      'attributes' => [
        'role' => 'button',
        'data-title' => $label,
      ],
    ];
    if ($is_all_active) {
      $all_link['attributes']['class'][] = 'active';
    }

    $menu['#links'] = ['media-library-menu-all' => $all_link] + $menu['#links'];

    return $menu;
  }

  /**
   * {@inheritdoc}
   *
   * Suppresses the add form on the "All media" tab.
   */
  protected function buildLibraryContent(MediaLibraryState $state) {
    if ($this->isAllMediaMode($state)) {
      return [
        '#type' => 'container',
        '#theme_wrappers' => ['container__media_library_content'],
        '#attributes' => ['id' => 'media-library-content'],
        'form' => [],
        'view' => $this->buildMediaLibraryView($state),
      ];
    }
    return parent::buildLibraryContent($state);
  }

  /**
   * {@inheritdoc}
   *
   * - Defaults to the table display.
   * - On the "All media" tab: removes the contextual argument, adds a hidden
   *   filter to enforce allowed types, and injects an exposed Media type filter.
   */
  protected function buildMediaLibraryView(MediaLibraryState $state) {
    $view = $this->entityTypeManager->getStorage('view')->load('media_library');
    $view_executable = $this->viewsExecutableFactory->get($view);

    $display_id = $state->get('views_display_id', 'widget_table');

    $view_request = $view_executable->getRequest();
    $view_request->query->add($state->all());
    $view_executable->setRequest($view_request);

    $view_executable->setDisplay($display_id);

    if ($this->isAllMediaMode($state)) {
      // Drop the contextual argument so the hidden filter below controls
      // which types are shown — this lets the exposed bundle filter work
      // independently without conflicting with argument OR-join logic.
      $view_executable->display_handler->setOption('arguments', []);
      $args = [];

      $filters = $view_executable->display_handler->getOption('filters') ?: [];

      // Hidden filter: restrict results to the field's allowed types only.
      $allowed_type_ids = $state->getAllowedTypeIds();
      $filters['mukurtu_allowed_bundles'] = [
        'id' => 'bundle',
        'table' => 'media_field_data',
        'field' => 'bundle',
        'relationship' => 'none',
        'group_type' => 'group',
        'admin_label' => '',
        'entity_type' => 'media',
        'entity_field' => 'bundle',
        'plugin_id' => 'bundle',
        'operator' => 'in',
        'value' => array_combine($allowed_type_ids, $allowed_type_ids),
        'group' => 1,
        'exposed' => FALSE,
        'expose' => [],
        'is_grouped' => FALSE,
        'group_info' => [],
      ];

      $view_executable->display_handler->setOption('filters', $filters);
    }
    else {
      $args = [$state->getSelectedTypeId()];
    }

    $view_executable->preExecute($args);
    $view_executable->execute($display_id);

    return $view_executable->buildRenderable($display_id, [], FALSE);
  }

}
