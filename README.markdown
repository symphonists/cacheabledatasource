# Cacheable Datasource
Version: 0.1  
Author: [Nick Dunn](http://nick-dunn.co.uk)  
Build Date: 07 December 2009  
Requirements: Symphony 2.0.6

Explorations from a forum discussion [Datasource Caching](http://symphony-cms.com/discuss/thread/32535/).

## Rationale

Some datasources simply execute a lot of database queries, and if you run a busy website then certain DSs may be a performance hit. Presently you have several options:

* reduce the number of fields and entries your DS is querying
* use the [Cachelite extension](http://symphony-cms.com/download/extensions/view/20455/) to cache the entire rendered HTML output of pages (useful to survive the Digg-effect)

However sometimes neither of these are viable. Perhaps you really *need* all of that data in your XML, or perhaps you have a "Logged in as {user}" notice in the header that means you can't cache the HTML output for all users.

This extension bundles a `CacheableDatasource` class from which your data sources can extend.

## Usage
Install this extension. Actual installation/enabling isn't strictly required, just having it in your /extensions/ folder is sufficient.

You now need to customise each datasource you want to cache. This will render the DS un-editable through the Data Source Editor (Blueprints > Components) but it's a small price to pay. If you're technically advanced to be using this extension in the first place, I'm assuming you're comfortable editing data sources by hand anyway.

1. Include the `CacheableDatasource` class at the top of your data source:

		require_once(EXTENSIONS . '/cacheabledatasource/lib/class.cacheabledatasource.php');

2. Change your data source class to extend `CacheableDatasource` instead of `Datasource`

		Class datasourcepage_articles extends CacheableDatasource {

3. Set the cache timeout in minutes:

		public $dsParamCACHE = '60';

4. Remove the grab() function from your data source.

5. Make the `allowEditorToParse()` function `return false` (or remove it altogether)

## Refresh your frontend page
View the `?debug` XML of your frontend page and you should see the cached XML and the age of the cache in seconds. The cached XML might jump to the top of the order in the XML source. This is normal, and is a by-product of how Symphony works out ordering on the fly.

## Refreshing the cache
Caches expire when their timeouts are met. You can manually purge the cache by looking into your `/manifest/cache` folder and deleting files with names beginning with the class name of your data source.