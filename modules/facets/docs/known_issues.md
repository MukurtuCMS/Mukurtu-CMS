# Known issues

When choosing the "Hard limit" option on a search_api_db backend, be aware that the limitation is done internally after sorting on the number of results ("num") first and then sorting by the raw value of the facet (e.g. entity-id) in the second dimension. This can lead to edge cases when there is an equal amount of results on facets that are exactly on the threshold of the hard limit. In this case, the raw facet value with the lower value is preferred:

| num | value | label |
|-----|-------|-------|
|  3  |   4   | Bar   |
|  3  |   5   | Atom  |
|  2  |   2   | Zero  |
|  2  |   3   | Clown |

"Clown" will be cut off due to its higher internal value (entity-id). For further details see: https://www.drupal.org/node/2834730
