# Using Twig Tweak to extend Views module functionality

Twig Tweak's `drupal_view()` method provides access to embed views within any
Twig code, including dynamically from within each row of another view. This
feature provides an alternate method to accomplish the nesting provided by the
[Views Field View](https://www.drupal.org/project/views_field_view) module.

The most basic syntax for Twig Tweak's view embed simply specifies the view, and
the machine name you wish to embed, as follows:
```twig
{{ drupal_view('who_s_new', 'block_1') }}
```

You can, however, also specify additional parameters which map to contextual
filters you have configured in your view.
```twig
{{ drupal_view('who_s_new', 'block_1', arg_1, arg_2, arg_3) }}
```

## Nested View Example
There are a lot of cases in views where you want to embed a list inside each
row. For example, when you have a list of product categories (taxonomy terms)
and for each category, you want to list the 3 newest products. In this example,
assume your content type is 'product', and has a reference field to a taxonomy
named 'product categories'.

### Step1: Create your child view
Create a view of the content of type 'product', and choose to create a block
displaying an unformatted list of teasers, with 3 items per block. In this
example, we'll use a view name of 'products_by_category'.  Once the view is
created, choose advanced and add a relationship to the taxonomy term that
references 'product categories', and choose to require the relationship. Next,
choose to add a "Contextual Filter" for "Term ID", and choose an action for when
the filter value is not available (e.g. display contents of no results found).
Set your desired sort and save your view.

### Step 2: Create your parent view
Create a view of taxonomy terms of type 'product categories', and choose to
create a page which displays an unformatted list of fields. Once this is
created, you should see the preview showing all the product categories. Choose
to add a field of type "Term ID", and choose "Exclude from display"; this is
necessary to make the term id available to the next field which uses Twig Tweak.
Now, choose to add a field of type "Custom text" from the "Global" category.
Inside that field, enter the Twig Tweak call to display the child view we
created above, passing the tid as a contextual filter, as such:
```twig
{{ drupal_view('products_by_category', 'block_1', tid) }}
```

You should now save your view, and be able to access the URL you assigned and
see a list of product categories, each followed by the three most recent
products within each.

This example can be applied to any nested view scenario, including
multiple-levels of nesting.

## Check if the view has results
```twig
{% set view = drupal_view_result('related', 'block_1')|length %}
{% if view > 0 %}
  {{ drupal_view('related', 'block_1') }}
{% endif %}
```
