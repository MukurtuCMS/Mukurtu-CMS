# Search API Solr

This module provides an implementation of the Search API which uses an Apache
Solr search server for indexing and searching. Before enabling or using this
module, you'll have to carefully read all the instructions given here.

The minimum support version Solr version is 6.4. Any version below might work
if you use your own Solr config or if you enable the optional
`search_api_solr_legacy` sub-module that is included in this module.

In general, it is highly recommended to run Solr in cloud mode (Solr Cloud)!

For a full description of the module, visit the
[project page](https://www.drupal.org/project/search_api_solr).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/search_api_solr).

Read the
[Search API Solr Documentation](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/search-api-solr).


## Table of contents

- Requirements
- Installation
- Local Solr Instance: : Solr Cloud - the modern way
- Local Solr Instance: : Solr (single core) - the classic way
- Local Solr Instance: : Solr Cloud - the classic way
- Using Jump-Start config-sets and docker images
- Updating Solr
- Search API Solr features
- Customizing your Solr server
- Troubleshooting Views
- Troubleshooting Facets
- Development
- Maintainers


## Requirements

This module requires the [Search API](https://www.drupal.org/project/search_api)
module, as well as several external libraries, which will be installed
automatically by Composer, which manages its dependencies.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

To connect a Solr server, follow the Search API documentation to add a Search
API Server and select Solr as backend.

The Search API Solr Backend supports different Solr Connector Plugins. Choose
and configure the one that matches your requirements. Some Solr service
providers require a special connector to be installed as third-party module.
(See the Connectors section below.)


## Local Solr Instance: Solr Cloud - the modern way

To setup Solr in Cloud mode locally you can either follow the instructions of
the [Apache Solr Reference Guide](https://solr.apache.org/guide/) or use
docker-compose with
https://github.com/docker-solr/docker-solr-examples/blob/master/docker-compose/docker-compose.yml

The preferred way for local development is to use DDEV where you can easily add
[ddev-solr](https://github.com/ddev/ddev-solr) using this command:

    $ ddev get ddev/ddev-solr
    $ ddev restart

For Drupal and Search API Solr you need to configure a Search API server using
Solr as backend and `Solr Cloud with Basic Auth` as its connector. As mentioned
above, username "solr" and password "SolrRocks" are the pre-configured
credentials for Basic Authentication in `.ddev/solr/security.json`.

Solr requires a Drupal-specific configset for any collection that should be used
to index Drupal's content. (In Solr Cloud "collections" are the equivalent to
"cores" in classic Solr installations. Actually, in a big Solr Cloud
installation a collection might consist of multiple cores across all Solr Cloud
nodes.)

Starting from Search API Solr module version 4.2.1 you don't need to deal with
configsets manually anymore. Just enable the `search_api_solr_admin` sub-module
which is part of Search API Solr. Now you create or update your "collections" at
any time by clicking the "Upload Configset" button on the Search API server
details page (see installation steps below), or use `drush` to do this with

    $ ddev drush --numShards=1 search-api-solr:upload-configset SEARCH_API_SERVER_ID

Note: Replace `SEARCH_API_SERVER_ID` with your Search API server machine name.
The number of "shards" should always be "1" as this local installation only
runs a single Solr node.

Note: The `search_api_solr_admin` sub-module and its features are not limited to
ddev! They work with any Solr Cloud instance as long as the required APIs are
not blocked by the hosting provider.

### Installation steps

1. Enable the `search_api_solr_admin` module. (This sub-module is included in Search API Solr >= 4.2.1)
2. Create a search server using the Solr backend and select `Solr Cloud with Basic Auth` as connector:
   - HTTP protocol: `http`
   - Solr node: `solr`
   - Solr port: `8983`
   - Solr path: `/`
   - Default Solr collection: `techproducts` (You can define any name here. The
     collection will be created automatically.)
   - Username: `solr`
   - Password: `SolrRocks`
3. On the server's "view" page click the `Upload Configset` button and check the "Upload (and overwrite) configset" checkbox.
4. Set the number of shards to `1`.
5. Press `Upload`.Once Solr is running with DDEV you don't need to deal with any configset


## Local Solr Instance: Solr (single core) - the classic way

In order for this module to work, you need to set up a Solr server.
For this, you can either purchase a server from a web Solr hosts or set up your
own Solr server on your web server (if you have the necessary rights to do so).
If you want to use a hosted solution, a number of companies are listed on the
module's [project page](https://drupal.org/project/search_api_solr). Otherwise,
please follow the instructions in this section.

Note: A more detailed set of instructions is available at:

- https://lucene.apache.org/solr/guide/8_4/installing-solr.html
- https://lucene.apache.org/solr/guide/8_4/taking-solr-to-production.html
- https://lucene.apache.org/solr/guide/ - list of other version specific guides

As a pre-requisite for running your own Solr server, you'll need a Java JRE.

Download the latest version of Solr 8.x from
https://lucene.apache.org/solr/downloads.html and unpack the archive
somewhere outside of your web server's document tree. The unpacked Solr
directory is named `$SOLR` in these instructions.

Note: Solr 6.x is still supported by search_api_solr but strongly discouraged.
That version has been declared end-of-life by the Apache Solr project and is
thus no longer supported by them.

**_Before_** creating the Solr core (`$CORE`) you will have to make sure it uses
the proper configuration files. They aren't always static but vary on your
Drupal setup.

But the Search API Solr Search module will create the correct configs for you!

1. Make sure you have Apache Solr started and accessible (i.e. via port 8983).
   You can start it without having a core configured at this stage.
2. Visit Drupal configuration (/admin/config/search/search-api) and create a
   new Search API Server according to the search_api documentation using
   "Solr" as Backend and the connector that matches your setup.
   Input the correct core name (which you will create at step 4, below).
3. Download the config.zip from the server's details page or by using
   `drush solr-gsc` with proper options, for example for a server named
   "my_solr_server": `drush solr-gsc my_solr_server config.zip 8.4`.
4. Copy the config.zip to the Solr server and extract. The unpacked
   configuration directory is named `$CONF` in these instructions.

**_Now_** you can create a Solr core using this config-set on a running Solr
server. There are different ways to do so. For most Linux distributions you can
run

`$ sudo -u solr $SOLR/bin/solr create_core -c $CORE -d $CONF -n $CORE`

You will see something like

    $ sudo -u solr /opt/solr/bin/solr create_core -c test-core -d /tmp/solr-conf -n test-core

Copying configuration to new core instance directory:
/var/solr/data/test-core

If you're forced to create the core before you can run Drupal to generate the
config-set you could also use the appropriate jump-start config-set you'll
find in the _jump-start_ directory of this module.

**You must not create a core without a proper drupal config-set!**
If you do so - even by accident - you won't recognize it immediately. But you'll
run into trouble like this soon:
[SolrException: Can not use FieldCache on multivalued field: boost_document](https://www.drupal.org/project/search_api_solr/issues/3056971)

Note: Every time you add a new language to your Drupal instance or add a custom
Solr Field Type you have to update your core configuration files. Using the
example above they will be located in /var/solr/data/test-core/conf. The Drupal
admin UI should inform you about the requirement to update the  configuration.
Reload the core after updating the config using
`curl -k http://localhost:8983/solr/admin/cores?action=RELOAD&core=$CORE` on
the command line or enable the search_api_admin sub-module to do it from the
Drupal admin UI.

Note: There's file called `solrcore.properties` within the set of generated
config files. If you need to fine tune some setting you should do it within this
file if possible instead of modifying `solrconf.xml`.

Afterward, go to `http://localhost:8983/solr/#/$CORE` in your web browser to
ensure Solr is running correctly.

CAUTION! For production sites, it is vital that you somehow prevent outside
access to the Solr server. Otherwise, attackers could read, corrupt or delete
all your indexed data. Using the server as described below WON'T prevent this by
default! If it is available, the probably easiest way of preventing this is to
disable outside access to the ports used by Solr through your server's network
configuration or through the use of a firewall.
Other options include adding basic HTTP authentication or renaming the solr/
directory to a random string of characters and using that as the path.

For configuring indexes and searches you have to follow the documentation of
search_api.


## Local Solr Instance: Solr Cloud - the classic way

Instead of a single core you have to create a collection in your Solr Cloud
instance. To do so you have to read the Solr handbook.

1. Create a Search API Server according to the search_api documentation using
   "Solr" or "Multilingual Solr" as Backend and the "Solr Cloud" or
   "Solr Cloud with Basic Auth" Connector.
1. Download the config.zip from the server's details page or by using
   `drush solr-gsc`
1. Deploy the config.zip via zookeeper.


### Using Linux specific Solr Packages

Note: The paths where the config.zip needs to be extracted to might differ from
the instructions above as well. For some distributions a directory like
_/var/solr_ or _/usr/local/solr_ exists.


## Local Solr Instance: Using Jump-Start config-sets and docker images

This module contains a _jump-start_ directory where you'll find a
docker-compose.yml files for various Solr versions. These use default
config-sets that will work for most drupal use-cases.
This variant is suitable for evaluation and development purposes.

These config-sets are also suitable for standard production use-cases without
the need for advanced features or customizations.

![Jump Start Config-Sets](https://github.com/mkalkbrenner/search_api_solr/workflows/Jump%20Start%20Config-Sets/badge.svg?branch=4.x)


## Updating Solr

Whenever you update your Solr installation it is recommended that you generate a
new config-set and deploy it. The deployment depends on the installation
variation you choose before. It is also recommended to re-index yur content
after an update. But if it is a minor update it should be safe to just queue all
content for re-indexing.

When performing a major version update like from Solr 6 to Solr 8 it is
recommended to delete the core or collection and recreate it like described in
the installation instructions above.


## Search API Solr features

All Search API datatypes are supported by using appropriate Solr datatypes for
indexing them.

The "direct" parse mode for queries will result in the keys being directly used
as the query to Solr using the
[Standard Parse Mode](https://lucene.apache.org/solr/guide/7_2/the-standard-query-parser.html).

Adding Devel module (and optionally, addons like Kint) provides the site with
a Solr Query Debugger and shows how content gets indexed.

Regarding third-party features, the following are supported:

- autocomplete
    - Introduced by module: search_api_solr_autocomplete
    - Lets you add autocompletion capabilities to search forms on the site.
- facets
    - Introduced by module: facet
    - Allows you to create facetted searches for dynamically filtering search
      results.
- more like this
    - Introduced by module: search_api
    - Lets you display items that are similar to a given one. Use, e.g., to create
      a "More like this" block for node pages build with Views.
- multisite
    - Introduced by module: search_api_solr
- spellcheck
    - Introduced by module: search_api_solr
    - Views integration provided by search_api_spellcheck
- attachments
    - Introduced by module: search_api_attachments
- location
    - Introduced by module: search_api_location
- NLP
    - Introduced by module: search_api_solr_nlp
    - Adds more fulltext field types based on natural language processing, for
      example field types that filter all word which aren't nouns. This is great
      for auto-completion.

If you feel some service option is missing, or have other ideas for improving
this implementation, please file a feature request in the project's issue queue,
at https://drupal.org/project/issues/search_api_solr.


### Processors

Please consider that, since Solr handles tokenizing, stemming and other
preprocessing tasks, activating any preprocessors in a search index' settings is
usually not needed or even cumbersome. If you are adding an index to a Solr
server you should therefore then disable all processors which handle such
classic preprocessing tasks.

If you create a new index, such processors won't be offered anymore since
8.x-2.0.

But the remaining processors are useful and should be activated. For example the
HTML filter or the Highlighting processor.

By default, the Highlighting processor provided by Search API uses PHP to create
highlighted snippets or an excerpt based on the entities loaded from the
database. Solr itself can do that much better, especially for different
languages. If you check `Retrieve result data from Solr` and `Highlight
retrieved data` on the index edit page, the Highlighting processor will use
this data directly and bypass it's own logic. To do the highlighting, Solr will
use the configuration of the Highlighting processor.


### Connectors

The communication details between Drupal and Solr is implemented by connectors.
This module includes:
  - Standard Connector
  - BasicAuth Connector
  - Solr Cloud Connector
  - Solr Cloud BasicAuth Connector

There are service provider specific connectors available, for example from
Acquia, Pantheon, hosted solr, platform.sh, and others:
  - [Hosted Solr](https://www.drupal.org/project/hosted_solr)
  - [SearchStax](https://www.drupal.org/project/search_api_searchstax)
  - [Pantheon](https://www.drupal.org/project/search_api_pantheon)
  - [Acquia](https://www.drupal.org/project/acquia_search)

Please contact your provider for details if you don't run your own Solr server.


## Customizing your Solr server

It's highly recommended that you don't modify the schema.xml and solrconfig.xml
files manually because this module dynamically generates them for you.

Most features that can be configured within these config files are reflected
by drupal configs that could be handled via drupal's own config management.

You can also create your own Solr field types by providing additional field
config YAML files. Have a look at this module's config folder to see examples.

Such field types can target a specific Solr version and a "domain". For example
"Apple" means two different things in a "fruits" domain or a "computer" domain.


## Troubleshooting Views

When displaying search results from Solr in Views using the Search API Views
integration, you have the choice to fetch the displayed values from Solr by
enabling "Retrieve result data from Solr" on the server edit page. Otherwise,
Solr will only return the IDs and Search API loads the values from the database.

If you decide to retrieve the values from Solr you have to enable "Skip item
access checks" in the query options in the views advanced settings. Otherwise,
the database objects will be loaded again for this check.
It's obvious that you have to apply required access checks during indexing in
this setup. For example using the corresponding processor or by having different
indexes for different user roles.

In general, it's recommended to *disable the Views cache*. By default, the Solr
search index is updated asynchronously from Drupal, and this interferes with the
Views cache. Having the cache enabled will cause stale results to be shown, and
new content might not show up at all.

In case you really need caching (for example because you are showing some search
results on your front page) then you use the 'Search API (time based)' cache
plugin. This will make sure the cache is cleared at certain time intervals, so
your results will remain relevant. This can work well for views that have no
exposed filters and are set up by site administrators.

Since 8.x-2.0 in combination with Solr 6.6 or higher you can also use the
'Search API (tag based)' cache. But in this case you need to ensure that you
enable "Finalize index before first search" and "Wait for commit after last
finalization" in the "Solr specific index options".

But be aware that this will slow down the first search after any modification to
an index. So you have to choose if no caching or tag based caching in
combination with finalization is the better solution for your setup.
The decision depends on how frequent index modification happen or how expensive
your queries are.

If you index some drupal fields multiple times in the same index and modify the
single values differently via our API before the values get indexed, you'll
notice that Views will randomly output the same value for all of these fields if
you enabled "Retrieve result data from Solr". In this case you have to enable
the "Solr dummy fields" processor and add as many dummy fields to the index as
you require. Afterward you should manipulate these fields via API.


## Troubleshooting Facets

Facetting on fulltext fields is not yet supported. We recommend the use of
string fields for that purpose.

Trie based field types were deprecated in Solr 6 and with Solr 7 we switched to
the point based equivalents. But lucene doesn't support a mincount of "0" for
these field types. We recommend the use of string fields instead of numeric ones
for that purpose.

If updating from Search API Solr 8.x-1.x or from Solr versions before 7 to Solr
7 or 8, check your Search API index' field configurations to avoid these errors
that will lead to exceptions and zero results.


## Development

Whenever you need to enhance the functionality you should do it using the API
instead of extending the SearchApiSolrBackend class!

To customize connection-specific things you should provide your own
implementation of the \Drupal\search_api_solr\SolrConnectorInterface.

A lot of customization can be achieved using YAML files and drupal's
configuration management.

We leverage the [solarium library](http://www.solarium-project.org/). You can
also interact with solarium's API using our hooks and callbacks or via event
listeners. This way you can for example add any solr specific parameter to a
query you need.

But if you create Search API Queries by yourself in code there's an easier way.
You can simply set the required parameter as option prefixed by 'solr_param_'.

So these two lines are "similar":

    $search_api_query->setOption('solr_param_mm', '75%');

    $solarium_query->setParam('mm', '75%');


### Patches and Issues Workflow

Our test suite includes integration tests that require a real Solr server. This
requirement can't be provided by the drupal.org test infrastructure.
Therefore, we leverage github workflows for our tests and had to establish a
more complex workflow:

1. Open an issue on drupal.org as usual
2. Upload the patch for being reviewed to that issue on drupal.org as usual
3. Fork https://github.com/mkalkbrenner/search_api_solr
4. Apply your patch and file a PR on github
5. Add a link to the github PR to the drupal.org issue

The PR on github will automatically be tested on github and the test results
will be reflected in the PR conversation.


### Running the test suite locally

This module comes with a suite of automated tests. To execute those, you just
need to have a (correctly configured) Solr instance running at the following
address:

    http://localhost:8983/solr/drupal

This represents a core named "drupal" in a default installation of Solr.

As long as you're changes don't modify the config-set generation you could
leverage docker, too. You'll find ready to use docker-compose files in the
_jump-start_ directory.

The tests themselves could be started by running something like this in your
drupal folder:

    $ phpunit -c core --group search_api_solr

(The exact command varies on your setup and paths.)


## Maintainers

- Markus Kalkbrenner - [mkalkbrenner](https://www.drupal.org/u/mkalkbrenner)
- Thomas Seidl - [drunken monkey ](https://www.drupal.org/u/drunken-monkey)
