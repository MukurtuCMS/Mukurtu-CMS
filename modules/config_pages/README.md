Do I need it?
------------
At some point i was tired of creating custom pages using menu and form api,
writing tons of code just to have a page with ugly form where client can
enter some settings, and as soon as client want to add some interactions
to the page (drag&drop, ajax etc) thing starts to get hairy.
If this sounds familiar then this module may be
just a thing you're looking for :)

Introduction
------------
This module provides fieldable entity that allows to create customizable
feature-rich configuration pages and place them where you like in menu
system, you are able to use fieldAPI with fine widgets created by community,
so multi values drag&drop, autocomplete, file uploads
looks pretty and just works out of the box!

Main features are:

- Fieldable entity (config page)
Create fieldable entity using BO, FieldAPI,
  Features and other entity modules are supported.

- Mount your config page into menu structure as you like
Your can choose how (where) user will access this config page,
  so it can have proper path like 'admin/config/mysettings' and not explain
  customer that he needs to create "a special node" in node/add.

- Context-awareness
You need to have same page with different settings based on current language
  or domain or some other factor? Config pages controller will automatically
  load proper config page based on current context. You can copy settings
  from one context to another, import and export text values using Features.
  Language and Domain (Domain module) contexts are supplied with this module,
  but you can add your custom context in no time using module's API.

- Create "singleton" pages
You no longer need to create a new content type that will store fields
  for your "singleton" pages like homepage and explain to client that this page
  is a content but he can't create 2 nodes in it.

- Nodequeue replacement.
In 95% cases using config pages will give you more flexibility than nodequeue
  module, if you use EntityReference field, and use views as autocomplete
  source, so you have all the power of views at your hands. And it will have
  all the features above - context awareness, themeable and more.

Installation
-------------
Install this module as any other module

Generic workflow
-------------------------------
1. After module installed you can navigate to
   "admin/structure/config_pages/types"
   and click on "Add config page"
2. Enter config page title and mount point, for example
   "admin/config/mysettings" and page type
3. Choose context if you need (if for example you want to have different
   settings for different languages) and save config page
4. Go to "admin/structure/config_pages/types" and find your config page there,
   you can add desired fields as you do for any other entity.
5. If this config page is expected to be rendered
   (for example if this is homepage config) manage display for your
   config page as you do for any other entity
6. At this point you can find you page using the path
   from pt2. (admin/config/mysettings)
7. Give appropriate user roles access to config page you created.
8. List of all created config page forms also can be found
   at "admin/structure/config_pages"

9. How can i access my settings?
9.1 If this config page is used to store settings then you
   should use config_pages_config() function.
