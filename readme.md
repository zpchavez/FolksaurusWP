Folksaurus WP
===============
Folksaurus WP is a WordPress plugin which allows your blog to interface with
[Folksaurus][], a site and web service providing access to a user-edited
controlled vocabulary.

[Folksaurus]: http://www.folksaurus.com

License
=======
Folksaurus WP is licensed under a modified BSD license.

Copyright (c) 2012, Zachary Chavez
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
   * Redistributions of source code must retain the above copyright
     notice, this list of conditions and the following disclaimer.
   * Redistributions in binary form must reproduce the above copyright
     notice, this list of conditions and the following disclaimer in the
     documentation and/or other materials provided with the distribution.
   * Neither the name of the copyright holder nor the name of any of the
     software's contributors may be used to endorse or promote products
     derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

Requirements
============
Folksaurus WP requires the [PholksaurusLib][] library and PHP 5.3 or higher.

[PholksaurusLib]: https://github.com/zpchavez/Pholksaurus-Lib

Setup
=====
You must follow these steps before Folksaurus WP will work.

1. Download [PholksaurusLib][] and copy the PholksaurusLib directory to a
   location in your include path.
2. [Obtain a Folksaurus API key][1] and add it to the config.ini file for
   PholksaurusLib.

[1]: http://www.folksaurus.com/profile/dev/register-app

Usage
=====
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

Feedback
========
Please post bug reports and feature requests to the [issue tracker][].

[issue tracker]:https://github.com/zpchavez/FolksaurusWP/issues