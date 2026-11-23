=== Desearch ===
Tags: search, web search
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Search the public web with Desearch from Tools > Desearch in your WordPress administration area.

== Description ==

Desearch adds a search page for administrators who can manage site settings. Save your Desearch API key, enter a query, and view linked result titles. No theme changes or public search endpoint are added.

You need a Desearch account and API key. Desearch service usage may require credits and is subject to account limits. Get a key at https://console.desearch.ai/api-keys.

= External service and privacy =

This plugin uses the Desearch hosted MCP service at https://mcp.desearch.ai/mcp. When an administrator presses Search, it sends the query and API key over HTTPS to run the search tool. The service also receives the site's server IP as part of the connection. No requests are sent on activation or when saving a key. The plugin does not store queries or results, send telemetry, or use cookies of its own. Site infrastructure, security plugins, or request logging tools may have their own logging behavior.

The key is stored in the site's WordPress options database, without autoload, and is not displayed again. Database administrators can read it. Blank input keeps the existing key; Remove the saved key deletes it. Deactivation keeps the setting; uninstall removes it. Each multisite site has its own setting.

Read the service terms at https://desearch.ai/terms and privacy policy at https://desearch.ai/privacy before using the service. The plugin does not promise any service retention policy beyond those documents.

== Installation ==

1. In Plugins > Add New Plugin > Upload Plugin, upload desearch-0.1.0.zip.
2. Activate Desearch.
3. Open Tools > Desearch. Review the external service notice, save your API key, and run a search.

== Frequently Asked Questions ==

= Who can use the plugin? =
Only users with the manage_options capability can open the page or run searches. On a standard site this means administrators.

= Is the API key encrypted? =
It is stored in the WordPress database, not encrypted by this plugin. Restrict database and hosting access. The saved key is never rendered into the form.

= Does it replace WordPress site search? =
It provides an administration tool for public web search. It does not change front-end site search.

= What if the key is invalid or the service is unavailable? =
The plugin shows a fixed error message without the key, query, or upstream error body. It does not retry automatically. Check the key and account limits before trying again.

== Changelog ==

= 0.1.0 =
* Initial administrator search page, saved API key, hosted MCP title and link results.
