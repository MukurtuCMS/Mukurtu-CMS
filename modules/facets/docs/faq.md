# FAQ


## Facets as a block

Q: Why do the facets disappear after a refresh?
A: We don't support cached views, change the view to disable caching.

Q: Why doesn't chosen (or similar JavaScript dropdown replacement) not work with the dropdown widget?
A: Because the dropdown we create for the widget is created through JavaScript, the chosen module (and others, probably) doesn't find the select element. Though the library can be attached to the block in custom code, we haven't done this in facets because we don't want to support all possible frameworks. See https://www.drupal.org/node/2853121 for more information.

Q: Why are facets results links from another language showing in the facet results?
A: Facets use the same limitations as the query object passed, so when using views, add a filter to the view to limit it to one language. Otherwise, this is solved by adding a `hook_search_api_query_alter()` that limits the results to the current language.

Q: I would like a prefix/suffix for facet result items.
A: If you just need to show text, use https://www.drupal.org/project/facets_prefix_suffix. However, if you need to include HTML you can use hook_preprocess_facets_result_item().

Q: Why are results shown for inaccessible content?
A: If the "Content access" Search API processor is enabled but results still aren't properly access-checked, you might need to write a custom processor to do the access checks for you. This should only happen if you're not using the default node access framework provided by Core, though. You need to use a combination of hook_node_grants and hook_node_access_records instead of hook_node_access.
