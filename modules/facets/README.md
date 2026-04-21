# Facets

The Facets module allows site builders to easily create and manage faceted
search interfaces.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/facets).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/facets).

## Table of contents 

- Requirements
- Recommended Modules
- Installation
- Configuration
- Features
- Extension modules
- FAQ
- Maintainers


## Requirements

No other modules are required; we're supporting Drupal Core's search as a
source for creating facets.


## Recommended Modules

- Search API - https://www.drupal.org/project/search_api


## Installation

Install the modules "Facets" and "Facets Exposed Filters" as you would
normally install a contributed Drupal module. Visit:
https://www.drupal.org/docs/extending-drupal/installing-modules
for further information.

Optionally, these modules are also available:

- Facets Range Widget
- Facets Searchbox Widget
- Facets summary
- Rest Facets  


## Configuration

1. Ensure that the 'Facets' and 'Facets Exposed Filters' modules are installed.
1. Create a [Search API](https://www.drupal.org/project/search_api) server
    and index with indexed data, or a page from the 
    [Search API Pages](https://www.drupal.org/project/search_api_page)
    module to use as facet source.
1. Create a View based on the search index and save it.
1. In the View, add a Facets enabled filter from the 'Facets' category.
1. Configure the Facet processors you want active on the Facet. E.g. enable 
    'Transform entity ID to label' if you indexed a taxonomy term ID and want to
    show the label.

Optionally, change the widget type under 'Advanced' > 'Exposed form'. For more
options, install 
[Better Exposed Filters](https://www.drupal.org/project/better_exposed_filters).
Install 
[Configurable Views Filter Block](https://www.drupal.org/project/configurable_views_filter_block)
to place filters anywhere.


## Known issues

When choosing the "Hard limit" option on a search_api_db backend, be aware that
the limitation is done internally after sorting on the number of results ("num")
first and then sorting by the raw value of the facet (e.g. entity-id) in the
second dimension. This can lead to edge cases when there is an equal amount of
results on facets that are exactly on the threshold of the hard limit. In this
case, the raw facet value with the lower value is preferred:

| num | value | label |
|-----|-------|-------|
|  3  |   4   | Bar   |
|  3  |   5   | Atom  |
|  2  |   2   | Zero  |
|  2  |   3   | Clown |

"Clown" will be cut off due to its higher internal value (entity-id). For
further details see: https://www.drupal.org/node/2834730


## Features

If you are the developer of a search API backend implementation and want
to support facets with your service class, too, you'll have to support the
"search_api_facets" feature. In short, when executing a query, you'll have to
return facet terms and counts according to the query's "search_api_facets"
option. For the module to be able to tell that your server supports facets,
you will also have to change your service's supportsFeature() method to
something like the following:

```
public function getSupportedFeatures() {
  return ['search_api_facets'];
}
```

If you don't do that, there's no way for the facet source to pick up facets.

The "search_api_facets" option looks as follows:

```
$query->setOption('search_api_facets', [
  $facet_id => [
    // The Search API field ID of the field to facet on.
    'field' => (string),
    // The maximum number of filters to retrieve for the facet.
    'limit' => (int),
    // The facet operator: "and" or "or".
    'operator' => (string),
    // The minimum count a filter/value must have been returned.
    'min_count' => (int),
    // Whether to retrieve a facet for "missing" values.
    'missing' => (bool),
  ],
  // …
]);
```

The structure of the returned facets array should look like this:

```
$results->setExtraData('search_api_facets', [
  $facet_id => [
    [
      'count' => (int),
      'filter' => (string),
    ],
    // …
  ],
  // …
]);
```

A filter is a string with one of the following forms:
- `"VALUE"`: Filter by the literal value VALUE (always include the quotes, not
  only for strings).
- `[VALUE1 VALUE2]`: Filter for a value between VALUE1 and VALUE2. Use
  parentheses for excluding the border values and square brackets for including
  them. An asterisk (*) can be used as a wildcard. E.g., (* 0) or [* 0) would be
  a filter for all negative values.
- `!`: Filter for items without a value for this field (i.e., the "missing"
  facet).


## Extension modules

- https://www.drupal.org/project/entity_reference_facet_link
  Provides a link to a facet through an entity reference field.
- https://www.drupal.org/project/facets_prefix_suffix
  Provides a plugin to configure a prefix/suffix per result.
- https://www.drupal.org/project/facets_block
  Provide the facets as a Drupal block.
- https://www.drupal.org/project/facets_taxonomy_path_processor
  Sets taxonomy facet items active if present in route.
- https://www.drupal.org/project/facets_view_mode_processor
  Provides a processor to render facet entity reference items as view modes.
- https://www.drupal.org/project/facets_range_input
  Provides an input range form (min and max) as a processor and widget.
- https://www.drupal.org/project/facets_range_dropdowns
  Provides a dropdown widget that works with the range processor.


## FAQ

**Q: Why doesn't chosen (or similar JavaScript dropdown replacement) not work
with the dropdown widget?**

**A:** Because the dropdown we create for the widget is created through JavaScript,
the chosen module (and others, probably) doesn't find the select element.
Though the library can be attached to the block in custom code, we haven't
done this in facets because we don't want to support all possible frameworks.
See https://www.drupal.org/node/2853121 for more information.

**Q: Why are facets results links from another language showing in the facet
results?**

**A:** Facets use the same limitations as the query object passed, so when using
views, add a filter to the view to limit it to one language.
Otherwise, this is solved by adding a `hook_search_api_query_alter()` that
limits the results to the current language.

**Q: I would like a prefix/suffix for facet result items.**

**A:** If you just need to show text, use
https://www.drupal.org/project/facets_prefix_suffix.
However, if you need to include HTML you can use
hook_preprocess_facets_result_item().

**Q: Why are results shown for inaccessible content?**

**A:** If the "Content access" Search API processor is enabled but results still
aren't properly access-checked, you might need to write a custom processor to do
the access checks for you.
This should only happen if you're not using the default node access framework
provided by Core, though. You need to use a combination of hook_node_grants and
hook_node_access_records instead of hook_node_access.


## Maintainers

- Joris Vercammen (borisson_) - https://www.drupal.org/u/borisson_
- Jimmy Henderickx (StryKaizer) - https://www.drupal.org/u/strykaizer
- Nick Veenhof (Nick_vh) - https://www.drupal.org/u/nick_vh
