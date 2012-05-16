Pholksaurus Lib
===============
Pholksaurus Lib is a PHP library for interfacing with [Folksaurus][], a site and
web service providing access to a user-edited controlled vocabulary.

[Folksaurus]: http://www.folksaurus.com

Pholksaurus Lib provides a high-level class which handles the management of
thesaurus data in your own app's database, keeping it in sync with the remote
Folksaurus service.  It also contains a lower level class for making requests
to Folksaurus directly.

License
=======
Pholksaurus Lib is licensed under a modified BSD license.

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
Pholksaurus Lib requires PHP 5.3 or greater, due to its use of namespaces.

Files
=====
The library is made up of the following components.

* TermManager.php
    > The main class containing methods for retrieving data from Folksaurus.

* config-template.ini
    > A template for the configuration file.  Most importantly, it contains
    > the API key for your application.  It also contains the expire_time
    > value, which determines how often your app will check Folksaurus for
    > updates.

* DataInterface.php
    > Defines an interface both in the programming language sense and in the
    > sense that its implementation interfaces with your app's database.
    > You must implement this interface in order for Pholksaurus Lib to know
    > how to retrieve term data from and save data to your database.

* Exception.php
    > Defines an Exception used by the library.

* init.php
    > A file which simply includes all the necessary files for using
    > Pholksaurus Lib.

* OOCurl.php
    > A slightly modified version of a library that provides an Object-Oriented
    > interface to PHP's cURL functions.

* RequestExecutor.php
    > Defines a class containing methods for making requests to Folksaurus.
    > This class is used by the TermManager class and you may not have a need to use it
    > directly.

* StatusCodes.php
    > Defines a class containing constants for the most likely to be
    > encountered HTTP status codes.

* Term.php
    > Defines a class whose instances represent Folksaurus terms.  Usually this
    > is what the methods in the TermManager class will return.

* TermSummary.php
    > Defines a less detailed version of the Term class which only contains the
    > term's name and ID, but none of the relationship data.  Term instances
    > use these to represent their related terms.

Setup
=====

Get an API Key
--------------

First you will need to [obtain a Folksaurus API key][1].

[1]: http://www.folksaurus.com/profile/dev/register-app

Set Up Config File
------------------

Once you have a key you need to create your config file by making a copy of
config-template.ini.  By default, Pholksaurus Lib will check for a file
called config.ini in the library's directory, but if you will be using
the library with multiple applications you can put the file elsewhere
and specify its path in the constructor to the TermManager object.

There are two other values in the config file.  You probably won't want
to change api_url.  The other value is expire_time, which specifies
how often to check Folksaurus for updates.  If you use the TermManager class
to retrieve a term and the time elapsed since its last_retrieved time
exceeds this value, then the latest term data will be requested.  Checking
too often may affect your app's performance and could cause your app to hit
usage limits.  The default is 8 hours.

Implement DataInterface
-----------------------

Next you will need to write an implementation of DataInterface.  The get
methods and deleteTerm method should be very straightforward, but special
consideration must be given to the saveTerm method.  When implementing this
method, make sure to handle the following cases:

   * In addition to saving the term itself, you will also need to save the
     relationships for the term.  Some of these relationships will be to
     terms that do not yet exist in your database.  In these cases you should
     create a new term with its name and IDs defined and with a
     last_retrieved value of 0.  That way, the full data for the term will
     be retrieved when needed.
   * Multiple terms with the same name are not allowed in Folksaurus, but it's
     possible for a conflict to arise in your database with terms that aren't
     current.  It might be best not to include a unique index on your term name
     column and let these conflicts sort themselves out the next time the
     conflicting term is updated.  Other solutions are possible.  Just be
     aware that it can happen.
   * When saving a term, you should note whether its status has changed from
     preferred to non-preferred.  If it has, you need to ensure that your
     resources are pointing to the preferred term.
   * A preferred term may also become ambiguous.  This means not only has it
     become non-preferred, but there are multiple possible preferred terms
     to which it could refer.  In this case you cannot automatically update
     what term your resources use because a human needs to look at them to
     decide which term is appropriate for each resource.  How exactly you
     handle this will depend on the nature of your application, but the main
     issue is that the correct preferred term cannot be substituted in
     automatically, so your app will need to alert users or whoever is
     responsible for tagging each resource that it needs to be changed.

Usage
=====
To load the library, simple include init.php.

The TermManager class may be the only class you work with directly.  When
creating an instance of it you pass a DataInterface instance to the
constructor.

When you call any of the get term methods in the TermManager class
(getTermByFolksaurusId, getTermByAppId, and getOrCreateTerm), the following
occurs.

1. A DataInterface method is called to attempt to find the term in your
   database.
2. If found, the term's last_retrieved date will be checked against the
   expire_time in your config file to determine whether to check Folksaurus
   for updates.  If the expire_time has not been exceeded, skip to step 5.
3. Assuming the expire_time has passed, a request for the latest term info
   is sent to Folksaurus.  If the term is not found, a create term
   request will be sent.  If the term is found but has not been modified
   since the expire_time, skip to step 5.
4. The changes to the term are saved to your database.
5. The term object is returned.

If a request to Folksaurus fails, the TermManager methods will still return the
object representing the term as it existed in your database.  If you need
to know the result of a request you can do the following.

```php
$statusCode = $termManager->getRequestExecutor()->getLatestResponseCode();
```