# Cheat sheet

## Drupal View
```twig
{{ drupal_view('who_s_new', 'block_1') }}
```
```twig
{# Specify additional parameters which map to contextual filters you have configured in your view. #}
{{ drupal_view('who_s_new', 'block_1', arg_1, arg_2, arg_3) }}
```

## Drupal View Result
Checks results for a given view. Note that the results themselves are not printable.
```twig
{% if drupal_view_result('cart')|length == 0 %}
  {{ 'Your cart is empty.'|t }}
{% endif %}
```

## Drupal Block
In order to figure out the plugin IDs list them using block plugin manager.
With Drush, it can be done like follows:
```shell
drush ev "print_r(array_keys(\Drupal::service('plugin.manager.block')->getDefinitions()));"
```
```twig
{# Print block using default configuration. #}
{{ drupal_block('system_branding_block') }}

{# Print block using custom configuration. #}
{{ drupal_block('system_branding_block', {label: 'Branding', use_site_name: false, id}) }}

{# Bypass block.html.twig theming. #}
{{ drupal_block('system_branding_block', wrapper=false) }}

{# For block plugin that has a required context supply a context mapping to tell the block instance where to get that context from. #}
{{ drupal_block('plugin_id', {context_mapping: {node: '@node.node_route_context:node'}}) }}
```

See [rendering blocks with Twig Tweak](blocks.md#block-plugin) for details.

## Drupal Region
```twig
{# Print 'sidebar_first' region of the default site theme. #}
{{ drupal_region('sidebar_first') }}

{# Print 'sidebar_first' region of Bartik theme. #}
{{ drupal_region('sidebar_first', 'bartik') }}
```

## Drupal Entity
```twig
{# Print a content block which ID is 1. #}
{{ drupal_entity('block_content', 1) }}

{# Print a node's teaser. #}
{{ drupal_entity('node', 123, 'teaser') }}

{# Print Branding block which was previously disabled on #}
{# admin/structure/block page. #}
{{ drupal_entity('block', 'bartik_branding', check_access=false) }}
```

## Drupal Entity Form
```twig
{# Print edit form for node 1. #}
{{ drupal_entity_form('node', 1) }}

{# Print add form for 'article' content type. #}
{{ drupal_entity_form('node', values={type: 'article'}) }}

{# Print user register form. #}
{{ drupal_entity_form('user', NULL, 'register', check_access=false) }}
```

## Drupal Field
Note that drupal_field() does not work for view modes powered by Layout Builder.
```twig
{# Render field_image from node 1 in view_mode "full" (default). #}
{{ drupal_field('field_image', 'node', 1) }}

{# Render field_image from node 1 in view_mode "teaser". #}
{{ drupal_field('field_image', 'node', 1, 'teaser') }}

{# Render field_image from node 1 and instead of a view mode, provide an array of display options. #}
{# @see https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Entity!EntityViewBuilderInterface.php/function/EntityViewBuilderInterface%3A%3AviewField #}
{{ drupal_field('field_image', 'node', 1, {type: 'image_url', settings: {image_style: 'large'}}) }}

{# Render field_image from node 1 in view_mode "teaser" in English with access check disabled. #}
{{ drupal_field('field_image', 'node', 1, 'teaser', 'en', FALSE) }}

{# Render field_image from node 1 in view_mode "full" (default) with access check disabled (named argument). #}
{{ drupal_field('field_image', 'node', 1, check_access=false) }}
```

## Drupal Menu
```twig
{# Print the top level of 'main' menu. #}
{{ drupal_menu('main') }}

{# Expand all menu links. #}
{{ drupal_menu('main', expand=true) }}
```

## Drupal Form
```twig
{{ drupal_form('Drupal\\search\\Form\\SearchBlockForm') }}
```

## Drupal Image
```twig
{# Render image specified by file ID. #}
{{ drupal_image(123) }}

{# Render image specified by file UUID. #}
{{ drupal_image('9bb27144-e6b2-4847-bd24-adcc59613ec0') }}

{# Render image specified by file URI. #}
{{ drupal_image('public://ocean.jpg') }}

{# Render image using 'thumbnail' image style and custom attributes. #}
{{ drupal_image('public://ocean.jpg', 'thumbnail', {alt: 'The alternative text'|t, title: 'The title text'|t}) }}

{# Render image using 'thumbnail' image style with lazy/eager loading (by attribute). #}
{{ drupal_image('public://ocean.jpg', 'thumbnail', {loading: 'lazy'}) }}
{{ drupal_image('public://ocean.jpg', 'thumbnail', {loading: 'eager'}) }}

{# Render responsive image (using a named argument). #}
{{ drupal_image('public://ocean.jpg', 'wide', responsive=true) }}
```

## Drupal Token

See Drupal\Core\Utility\Token::replace()
```twig
{# Global tokens: #}
{{ drupal_token('site:name') }}

{# Tokens with required data (2nd parameter): #}
{{ drupal_token('node:title', {node: node}) }}

{# Tokens with required data (2nd parameter) and options (3rd parameter): #}
{{ drupal_token('node:title', {node: node}, {clear: true}) }}
```

## Drupal Config
```twig
{{ drupal_config('system.site', 'name') }}
```

## Drupal Dump
```twig
{# Basic usage. #}
{{ drupal_dump(var) }}

{# Same as above but shorter. #}
{{ dd(var) }}

{# Dump all available variables for the current template. #}
{{ dd() }}
```

## Drupal Title
```twig
{# The title is cached per URL. #}
{{ drupal_title() }}
```

## Drupal URL
```twig
{# The function accepts a valid internal path, such as "/node/1", "/taxonomy/term/1", a query string like "?query," or a fragment like "#anchor". #}

{# Basic usage. #}
{{ drupal_url('node/1') }}

{# Complex URL. #}
{{ drupal_url('node/1', {query: {foo: 'bar'}, fragment: 'example', absolute: true}) }}
```

## Drupal Link
```twig
{# It supports the same options as drupal_url(), plus attributes. #}
{{ drupal_link('View'|t, 'node/1', {attributes: {target: '_blank'}}) }}

{# This link will only be shown to the privileged users. #}
{{ drupal_link('Example'|t, '/admin', check_access=true) }}
```

## Drupal Messages
```twig
{{ drupal_messages() }}
```

## Drupal Breadcrumb
```twig
{{ drupal_breadcrumb() }}
```

## Drupal Breakpoint
```twig
{# Make Xdebug break on the specific line in the compiled Twig template. #}
{{ drupal_breakpoint() }}
```

## Contextual Links
```twig
{# Basic usage. #}
<div class="contextual-region">
  {{ drupal_contextual_links('entity.view.edit_form:view=frontpage:display_id=feed_1') }}
  {{ drupal_view('frontpage') }}
</div>
{# Multiple links. #}
<div class="contextual-region">
  {{ drupal_contextual_links('node:node=123:|block_content:block_content=123:') }}
  {{ content }}
</div>
```

## Token Replace
```twig
{# Basic usage. #}
{{ '<h1>[site:name]</h1><div>[site:slogan]</div>'|token_replace }}

{# This is more suited to large markup. #}
{% apply token_replace %}
  <h1>[site:name]</h1>
  <div>[site:slogan]</div>
{% endapply %}
```

## Preg Replace
```twig
{{ 'Drupal - community plumbing!'|preg_replace('/(Drupal)/', '<b>$1</b>') }}
```
For simple string interpolation consider using built-in `replace` or `format`
Twig filters.

## Image Style
```twig
{# Basic usage #}
{{ 'public://images/ocean.jpg'|image_style('thumbnail') }}

{# Make sure to check that the URI is valid #}
{% set image_uri = node.field_media_optional_image|file_uri %}
{% if image_uri is not null %}
  {{ image_uri|image_style('thumbnail') }}
{% endif %}
```
`image_style` will trigger an error on invalid or empty URIs, to avoid broken
images when used in an `<img/>` tag.

## Transliterate
```twig
{{ 'Привет!'|transliterate }}
```

## Check Markup
```twig
{{ '<b>bold</b> <strong>strong</strong>'|check_markup('restricted_html') }}
```

## Format size
Generates a string representation for the given byte count.
```twig
{{ 12345|format_size }}
```

## Truncate
```twig
{# Truncates a UTF-8-encoded string safely to 10 characters. #}
{{ 'Some long text'|truncate(10) }}

{# Same as above but with respect of words boundary. #}
{{ 'Some long text'|truncate(10, true) }}
```

## View
```twig
{# Do not put this into node.html.twig template to avoid recursion. #}
{{ node|view }}
{{ node|view('teaser') }}

{{ node.field_image|view }}
{{ node.field_image[0]|view }}
{{ node.field_image|view('teaser') }}
{{ node.field_image|view({settings: {image_style: 'thumbnail'}}) }}
```

## With
This is an opposite of core `without` filter and adds properties instead of removing it.
```twig
{# Set top-level value. #}
{{ content.field_image|with('#title', 'Photo'|t) }}

{# Set nested value. #}
{{ content|with(['field_image', '#title'], 'Photo'|t) }}
```

## Data URI
The filter generates a URL using the data scheme as defined in [RFC 2397](https://datatracker.ietf.org/doc/html/rfc2397)
```twig
{# Inline image. #}
<img src="{{ '<svg xmlns="http://www.w3.org/2000/svg"><rect width="100" height="50" fill="lime"/></svg>'|data_uri('image/svg+xml') }}" alt="{{ 'Rectangle'|t }}"/>
{# Image from file system. #}
<img src="{{ source(directory ~ '/images/logo.svg')|data_uri('image/svg+xml') }}" alt="{{ 'Logo'|t }}"/>
```

## Children
```twig
<ul>
  {% for tag in content.field_tags|children %}
    <li>{{ tag }}</li>
  {% endfor %}
</ul>
```

## File URI
When field item list is passed, the URI will be extracted from the first item.
In order to get URI of specific item specify its delta explicitly using array
notation.
```twig
{{ node.field_image|file_uri }}
{{ node.field_image[0]|file_uri }}
```

Media fields are fully supported including OEmbed resources, in which case
it will return the URL to the resource, similar to the `file_url` filter.
```twig
{{ node.field_media|file_uri }}
```

## File URL
For string arguments it works similar to core `file_url()` Twig function.
```twig
{{ 'public://sea.jpg'|file_url }}
```

In order to generate absolute URL set "relative" parameter to `false`.
```twig
{{ 'public://sea.jpg'|file_url(false) }}
```

When field item list is passed, the URL will be extracted from the first item.
In order to get URL of specific item specify its delta explicitly using array
notation.
```twig
{{ node.field_image|file_url }}
{{ node.field_image[0]|file_url }}
```

Media fields are fully supported including OEmbed resources.
```twig
{{ node.field_media|file_url }}
```

It is also possible to extract file URL directly from an entity.
```twig
{{ image|file_url }}
{{ media|file_url }}
```

## Entity URL
Gets the URL object for the entity.
See \Drupal\Core\Entity\EntityInterface::toUrl()
```twig
{# Creates canonical URL for the node. #}
{{ node|entity_url }}

{# Creates URL for the node edit form. #}
{{ node|entity_url('edit-form') }}
```

## Entity Link
Generates the HTML for a link to this entity.
See \Drupal\Core\Entity\EntityInterface::toLink()
```twig
{# Creates a link to the node using the node's label. #}
{{ node|entity_link }}

{# Creates link to node comment form. #}
{{ node|entity_link('Add new comment'|t, 'canonical', {fragment: 'comment-form'}) }}
```

## Entity translation
That is typically needed when printing data from referenced entities.
```twig
{{ media|translation.title|view }}
```

## Cache metadata
When using raw values from entities or render arrays it is essential to
ensure that cache metadata are bubbled up.
```twig
<img src="{{ node.field_media|file_url }}" alt="Logo"/>
{{ content.field_media|cache_metadata }}
```

## PHP
PHP filter is disabled by default. You can enable it in `settings.php` file as
follows:
```php
$settings['twig_tweak_enable_php_filter'] = TRUE;
```
```twig
{{ 'return date('Y');'|php }}
```

Using PHP filter is discouraged as it may cause security implications. In fact,
it is very rarely needed. The above code can be replaced with the following.
```twig
{{ 'now'|date('Y') }}
```
