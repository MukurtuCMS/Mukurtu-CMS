About
-----

The Search API Solr Legacy module allows to connect to older/unsupported Solr
versions.
It is not guaranteed to be supported for a long time but should provide an
easier migration to recent Solr versions throughout 2020.


Features and Compatibility
--------------------------

Search API Solr 3.x was designed for Solr 6 and newer. Nevertheless Search API
Solr Legacy manages to provide most of the features for Solr 4 and 5, too.

There're only a few incompatible and therefore not supported features you need
to be aware of. If possible the UI has been adjusted accordingly. But some
features could still be configured and will then lead to runtime errors or
unexpected results.
In a multilingual setup, all languages are supported but some aren't working as
good compared to Solr 6 and above.
But over all you get more features compared to Search API Solr 1.x and Search
API Solr Multilingual 1.x.

Solr 5 doesn't support ...
* suggesters
* suggesters based autocomplete

Solr 4 doesn't support ...
* suggesters
* suggesters based autocomplete
* spellcheck based autocomplete
* Search API Location
* Date Range field type and processor


Supported Solr Versions
-----------------------

This minimum required Solr version this module supports is Solr 4.5. So it
should (in theory) support
* 4.5.0
* 4.5.1
* 4.6.0
* 4.6.1
* 4.7.0
* 4.7.1
* 4.7.2
* 4.8.0
* 4.8.1
* 4.9.0
* 4.9.1
* 5.0.0
* 5.1.0
* 5.2.0
* 5.2.1
* 5.3.0
* 5.3.1
* 5.3.2
* 5.4.0
* 5.4.1
* 5.5.0
* 5.5.1
* 5.5.2
* 5.5.3
* 5.5.4
* 5.5.5

However, the automated tests are only run against Solr 4.5.1 and 5.5.5!

The search_api_solr main module only supports Solr 6.4 and newer. So there's a
gap and these Solr versions aren't natively supported:
* 6.0.0
* 6.0.1
* 6.1.0
* 6.2.0
* 6.2.1
* 6.3.0

If you really require to run one of these early Solr 6 versions you should
configure the Search API Server to connect a Solr 5.x server. Every Solr version
is backward compatible to the previous major version. Therefore you could expect
better results running such an early Solr 6 version using a 5.x config-set
compared to to try to get a Solr 6.4 config-set to work with Solr 6.0 - 6.3.
To do so open the edit form of your Search API Server and open "Connector
Workarounds". Set "Solr version override" from "Determine automatically" to
"5.x".
