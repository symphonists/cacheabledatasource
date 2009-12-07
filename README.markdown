# Cacheable Datasource
Version: 0.1  
Author: [Nick Dunn](http://nick-dunn.co.uk)  
Build Date: 07 December 2009  
Requirements: Symphony 2.0.6

Explorations from a forum discussion [Datasource Caching](http://symphony-cms.com/discuss/thread/32535/).

## DISCLAIMER
This shit is relatively untested. Use with caution. If you hit problems, please try and debug and send patches via Github. This will not be released onto the Symphony site as an extension until stability can be verified!

## Rationale

Some datasources simply execute a lot of database queries, and if you run a busy website then certain DSs may be a performance hit. Presently you have several options:

* reduce the number of fields and entries your DS is querying
* use the [Cachelite extension](http://symphony-cms.com/download/extensions/view/20455/) to cache the entire rendered HTML output of pages (useful to survive the Digg-effect)

However sometimes neither of these are viable. Perhaps you really *need* all of that data in your XML, or perhaps you have a "Logged in as {user}" notice in the header that means you can't cache the HTML output for all users.

This extension bundles a `CacheableDatasource` class from which your data sources can extend.

## How do I use it?
Install this extension. Actual installation/enabling isn't strictly required, just having it in your /extensions/ folder is sufficient.

You now need to customise each datasource you want to cache. This will render the DS un-editable through the Data Source Editor (Blueprints > Components) but it's a small price to pay. If you're technically advanced to be using this extension in the first place, I'm assuming you're comfortable editing data sources by hand anyway.

1. Include the `CacheableDatasource` class at the top of your data source:

		require_once(EXTENSIONS . '/cacheabledatasource/lib/class.cacheabledatasource.php');

2. Change your data source class to extend `CacheableDatasource` instead of `Datasource`

		Class datasourcepage_articles extends CacheableDatasource {

3. Set the cache timeout in minutes:

		public $dsParamCACHE = 30;

4. Remove the grab() function from your data source.

5. Make the `allowEditorToParse()` function `return false` (or remove it altogether)

## Refresh your frontend page
View the `?debug` XML of your frontend page and you should see the cached XML and the age of the cache in seconds. The cached XML might jump to the top of the order in the XML source. This is normal, and is a by-product of how Symphony works out ordering on the fly.

## Are Output Parameters supported?
Yes! If a DS outputs parameters into the param pool then these are cached along with the XML. *However* the implementation of Output Params may change in Symphony 2.1, so this extension will probably need to be updated when the time comes.

## How do I purge the cache?
Caches expire when their timeouts are met. You can manually purge the cache by looking into your `/manifest/cache` folder and deleting files with names beginning with the class name of your data source.

## Why are so many cache files created?
Cache files are never deleted, only overwritten when they have expired. It is normal to have many files generated for each data source since the filename is a hashed signature of all of its configuration properties and filters. This means that if you have pagination enabled, a cache file is generated for each page of results since each page creates a unique combination of filters.

It works this way to allow for very wide, rather than narrow, hierarchies. Say you have a site showcasing bands, 10,000 in total. Your Band page accepts an artist entry ID and filters the Bands section to show that single entry. For this wide sitemap, you would require each instance of the Band Profile datasource to be cached individually. Which it is :-)