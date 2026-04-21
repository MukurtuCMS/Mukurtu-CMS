1. Add a Search API index for the server you configured in step 2.
   1. Check "Solr Document" under the list of Data Sources.
   2. Configure the Solr Document Datasource by specifying the name of a unique
      ID field from your server's schema.
   3. Finish configuring the index by selecting the appropriate server. Check
      "Read only" under the Index Options.
2. On the index's Fields tab, add the fields you would like to have shown in
   Views or any other display.  Fields must be added here first before they can
   be displayed, despite appearing in the Add fields list in Views.
3. Create a view or some other display to see the documents from your server.
   If you are using Views, set it up as you would for any other Search API
   datasource.
   1. In the Add view wizard, select "Index INDEX_NAME" under the View
      Settings.
   2. Configure the view to display fields.
   3. Add the fields that you want to display.

Known Issues
------------
* Search API Solr's backend unsets the ID field that you configure in the
  datasource settings from the results arrays, making it unavailable for
  display.
