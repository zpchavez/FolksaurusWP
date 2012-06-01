=== Folksaurus WP ===
Contributors: zpchavez
Tags: taxonomy, folksaurus, controlled vocabulary, thesaurus
Requires at least: 3.0.0
Tested up to: 3.3.2
Stable tag: 1.0
License: BSD
License URI: https://github.com/zpchavez/FolksaurusWP/blob/master/LICENSE

Folksaurus WP is a WordPress plugin which allows your blog to interface with
the Folksaurus web service.


== Description ==

Folksaurus WP is a WordPress plugin which allows your blog to interface with
Folksaurus, a site and web service providing access to a user-edited controlled
vocabulary.

Folksaurus WP adds two new tables to your database, one containing term details
and one containing relationship data.

Every time term data is loaded for display on your WordPress site, the
timestamp of when the term's details were last requested are checked against
the expire_time value from PholksaurusLib's config.ini file.  If the term
is out of date, a new request is made to Folksaurus and the updated data is
saved to the term_data and term_relationships tables.

In addition, new terms will be added to your database for every term related
to the term being updated.

Terms will have their parent_id values set reflecting the broader and
narrower term relationships.

If the preferred term for a concept changes, the object relationships in your
database will be changed so that they use the new preferred term.

When logged in as an admin or editor, term links displayed at the bottom of
posts will be styled differently if they are deleted, ambiguous, or
non-preferred (non-preferred terms may still appear temporarily, despite the
feature described in the previous paragraph).

== Installation ==

Folksaurus WP requires the [PholksaurusLib][1] library and PHP 5.3 or higher.

You must follow these steps before Folksaurus WP will work.

1. Download [PholksaurusLib][1] and copy the PholksaurusLib directory to a
   location in your include path.
2. [Obtain a Folksaurus API key][2] and add it to the config.ini file for
   PholksaurusLib.

[1]: https://github.com/zpchavez/Pholksaurus-Lib
[2]: http://www.folksaurus.com/profile/dev/register-app


== Changelog ==

In development.

== Upgrade Notice ==

In development.

== Feedback ==
Please post bug reports and feature requests to the [issue tracker][].

[issue tracker]:https://github.com/zpchavez/FolksaurusWP/issues
