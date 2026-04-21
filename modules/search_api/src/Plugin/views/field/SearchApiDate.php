<?php

namespace Drupal\search_api\Plugin\views\field;

use Drupal\views\Attribute\ViewsField;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\views\Plugin\views\field\Date;
use Drupal\views\Plugin\views\field\MultiItemsFieldHandlerInterface;

/**
 * Handles the display of date fields in Search API Views.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField('search_api_date')]
class SearchApiDate extends Date implements MultiItemsFieldHandlerInterface {

  use SearchApiFieldTrait {
    render_item as trait_render_item;
  }

  /**
   * Renders a single item of a row.
   *
   * \Drupal\views\Plugin\views\field\Date::render() assumes values are always
   * timestamps, so here we make sure this is indeed the case.
   *
   * @param int $count
   *   The index of the item inside the row.
   * @param mixed $item
   *   The item for the field to render.
   *
   * @return string
   *   The rendered output.
   *
   * @see \Drupal\views\Plugin\views\field\MultiItemsFieldHandlerInterface::render_item()
   *
   * @see \Drupal\views\Plugin\views\field\Date::render()
   */
  public function render_item($count, $item) {
    if (!empty($item['value']) && !is_numeric($item['value'])) {
      try {
        $timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
        $date_time = new \DateTime($item['value'], $timezone);
        $item['value'] = $date_time->getTimestamp();
      }
      catch (\Exception $e) {
        $this->logException($e, '%type while trying to parse date value (Views field "@field_id"): @message in %function (line %line of %file).', ['@field_id' => $this->options['id']]);
        return NULL;
      }
    }
    return $this->trait_render_item($count, $item);
  }

}
