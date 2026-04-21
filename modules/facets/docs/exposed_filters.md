# Facets Exposed Filters

## Why?

* Native ajax support handled by Views.
* Better site builder UX. Facets are created similar to other Views filters, directly in the Views UI.
* Better performance. the actual Views query is the only query fired.
* Multiple displays support, no need to recreate the same facets.
NOTE: Overridden display filters are not (yet) supported.
* Widgets are handled by Better Exposed Filters. No special widgets required for Facets.
* Facet processors are still supported.
* Advanced setups (e.g. render a View with Facets, using Layout builder/Paragraphs) are supported out of the box.


## How to create a Facet filter in the Views UI?

Not changed since Facets 2, you still need to:

  * Ensure you have a search index and server set up.
  * Add the field you want a facet on in your search api index and ensure it is indexed.

New since Facets 2:

  * Enable the submodule "Facets Exposed Filters"
  * Create a view based on the search index.
  * Save the view. (If you do not save it first, the facet settings are not available).
  * Add a 'filter criteria' of your field, which has 'Facets' as category. If you did not save the view first, you will now see a warning that you need to save the view before you can edit the facet settings.
  * Configure which Facet processors you want active on the Facet. E.g. enable "Transform entity ID to label" if you indexed a taxonomy term id and want to show the label. You can change many settings in this screen.
  * Optionally, change the widget type in "Advanced": "Exposed form"

## How do I use feature X from Facets 2.x

* Place Facets in separate regions? See [Configurable views filter block](#configurable-views-filter-block)
* Use Facets Summary? See [Views filters summary](#views-filters-summary)
* Use hierarchical facets? See [How to do hierarchical facets](#hierarchical-facets)
* Auto submit Facets and hide submit buttons? See [Better exposed filters](#better-exposed-filters)
* Use checkboxes, links, dropdown? See [Better exposed filters](#better-exposed-filters)


## Upgrade from Facets 2.x or Facet blocks

### Should I upgrade?

There is no automatic upgrade path. Upgrading is not required. Facet blocks will stay supported, however, no AJAX on Facet Blocks is supported.

### Things you need to do to upgrade

* Since Facets Exposed Filters uses native views elements, you will need to check if the output fits your design and adjust the accordingly.
* Recreate all facets in the Views UI.
* Delete the facets block on /admin/config/search/facets

## Suggested modules

### Views filters summary
Replaces the "facets_summary" module, and even allows non-facet filters in the summary.
[Link to module](https://www.drupal.org/project/views_filters_summary)

### Views AJAX History
Updates URLs when using AJAX in Views to make them bookmarkable.
[Link to module](https://www.drupal.org/project/views_ajax_history)

### Views Dependent Filters
Allows you to hide/show filters depending on values of other filters.
[Link to module](https://www.drupal.org/project/views_dependent_filters)

### Configurable views filter block
Allows you to place filters in separate regions. Not only limited to Facets, but also supports exposed sorts, pagers and other filters.
[Link to module](https://www.drupal.org/project/configurable_views_filter_block)

### Better Exposed Filters
Allows different widgets per filter (e.g. links, checkboxes, dropdown), ...
Offers optional auto-submit and hide submit buttons.
[Link to module](https://www.drupal.org/project/better_exposed_filters)

## Hierarchical facets
Ensure your hierarchy is indexed in the search index. You need to enable the "Index hierarchy" processor for this, and configure the fields.
In Views UI, you can enable "Build hierarchical tree" in the Facet settings. Extra settings will be visible to configure how your tree will behave.

## For developers
TODO: Document the hidden views_default search api Views Display.
