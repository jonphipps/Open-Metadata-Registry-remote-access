http://ptlis.net/source/php/content-negotiation/

content negotiation
purpose
current release
licence
capabilities
usage
basics
ua quality factors only
app quality factors
server response
agent-driven
server-driven
notes
compatibility
downloads
changelog
feedback
purpose

The library described in this document was developed with the goal of facilitating the development of 'smart' web applications that take advantage of the information most user agents provide with each HTTP request to serve the most appropriate resource to the user agent (language, compression, character-set and mime-type).

current release

The version number of the current release is 2.0.0, this code is mature and there are no known issues.

licence

This library is free software; you can redistribute it and/or modify it under the terms of the GNU Lesser General Public License as published by the Free Software Foundation; either version 2.1 of the License, or (at your option) any later version.

capabilities

This library provides the means of performing (Server-Driven and Agent-Driven) Content Negotiation on the Accept, Accept-Charset, Accept-Encoding and Accept-Language fields in a HTTP header.

Wildcards are supported; the library handles the precedence of exact matches and wildcard matches. If negotiation is being performed on the Accept field subtype wildcard matches are supported.

Quality factors are supported with the error-handling caveats described in the notes secion.

If Accept negotiation is being performed then accept-extension paramaters are also supported. Handling the of order of precedence for the specificness of the resource is supported (eg. if text/html & text/html;level=4.01 have identical quality factors the latter has precedence).
usage

The usage examples below are for negotiation being performed on the Accept-Charset field to determine how to encode a textual resource. Negotiation performed on other fields follows the same pattern described below.

The Accept-Charset field used to generate the example output is:

Accept-Charset: iso-8859-1,*;q=0.5,utf-8;q=0.8
basics

The functionality provided can be used in two ways; Firstly, if called with no parameters, the functions will return a sorted array of types and quality factors provided by the user agent. If your application has specific variations on a resource and needs to know which to serve then the functions can be provided with an array of types that your application supports and their associated quality factors (ranging from 0-1, unique). The library will determine the user agent's quality factors for these types and order the array by the product of both.

To use the class in your applications you must include the content negotiation source file (assuming the library is in the same directory as the php script being executed):

require_once 'conNeg/PHP5.x/conNeg.inc.php';
ua quality factors only

Although this alone isn't content negotiation, it can be useful to call the appropriate function without providing application quality factors; as stated above this simply calculates what the user-agent prefers and orders the types by quality factor.

If only the best match is required then the conNeg::charBest() function should be called:

$charsetBest = conNeg::charBest();
With the stated contents of the Accept-Charset field the output would be iso-8859-1.

If the generated datastructure is required then simply call conNeg::charAll():

$charsetAll = conNeg::charAll();
The output of the function (again with the stated Accept-Charset contents) would be:

Array
(
    [type] => Array
        (
            [0] => iso-8859-1
            [1] => utf-8
            [2] => utf-16
        )

    [qFactorUser] => Array
        (
            [0] => 1
            [1] => 0.8
            [2] => 0.5
        )
)
app quality factors

The application's quality factors can be provided in one of two ways, the first is in the form of a multi-dimensional array structured as below (array keys indicate the correlation between the type and the quality factor):

$charsetFactors['type'][0]       = 'UTF-8';
$charsetFactors['qFactorApp'][0] = 1;

$charsetFactors['type'][1]       = 'iso-8859-1';
$charsetFactors['qFactorApp'][1] = 0.9;

$charsetFactors['type'][2]       = 'UTF-16';
$charsetFactors['qFactorApp'][2] = 0.5;
The array in the 'type' element is an array of character sets that your application supports, and the 'qFactorApp' element is an array of preferences that your application has for that type; these values MUST be different from each other and range from 0-1.

Alternatively the application can provide it's type preferences in the form of a HTTP field; below is the same preference data expressed as a Accept-Charset field:

$charsetFactors = 'UTF-8,iso-8859-1;q=0.9,UTF-16;q=0.5';
If only the best match is required then the conNeg::charBest() function should be called:

$charsetBest = conNeg::charBest($charsetFactors);
With the stated Accept-Charset header the output of the function would be iso-8859-1.

Alternatively the generated datastructure can be retrieved by using the conNeg::charAll() function:

$charsetAll = conNeg::charAll($charsetFactors);
The output of the function (again with the stated Accept-Charset header) would be:

Array
(
    [type] => Array
        (
            [0] => iso-8859-1
            [1] => utf-8
            [2] => utf-16
        )

    [qFactorProduct] => Array
        (
            [0] => 0.9
            [1] => 0.8
            [2] => 0.25
        )

    [qFactorApp] => Array
        (
            [0] => 0.9
            [1] => 1
            [2] => 0.5
        )

    [qFactorUser] => Array
        (
            [0] => 1
            [1] => 0.8
            [2] => 0.5
        )
)
The contents of qFactorProduct are what the types are ordered by. By combining the application and user-agent quality factors we find the best match for types the application can serve and the user-agent accepts.

server response

With negotiation performed and the preferred character set stored in $charsetBest the application can perform a number of actions depending upon what method of negotiation is to be implemented.

agent-driven

If agent-driven negotiation is being performed then the server response would generally be a simple HTML document with the hyperlinks to the valid representations of the resource. The server should also send the 300 (Multiple Choices) status code:

header('HTTP/1.1 300 Multiple Choices');
If there is no representation of the resource that conforms to the characteristics defined in the accept fields of the request then a 406 (Not Acceptable) status code should be returned as well as a HTML document with hyperlinks to available representations from which the user may select:

header('HTTP/1.1 406 Not Acceptable');
server-driven

If server-driven negotiation is being performed there are two possible server responses; if the representations of the resource have distinct URIs (as is the case of agent-driven negotiation) the server can send this URI in the Location field of it's response alongside the 303 (See Other) status code and have the client redirected transparently:

header('HTTP/1.1 303 See Other');
header('Location: /path/to/resource/');
If, however, there is a single URI to address all representations of the resource the application must set the Vary field appropriately to indicate to caching agents that content negotiation has been performed on the Accept-Charset field:

header('Vary: Accept-Charset');
It must then update the Content-Type field it sends (assuming the resource being served is a html document):

header('Content-Type: text/html; charset=' . $charsetBest);
notes

The specification does not descibe how to respond to fields in a HTTP request with invalid contents so where feasible the parser is lenient. For example, if a type is found in the header with a partially malformed quality factor such as text/html;q=0.5c the c is ignored and the quality factor evaluates to 0.5. Malformed quality factors containing any string not beginning with a digit are evaluated to 1, as if no quality factor was provided.

All string data that the library handles is converted to lowercase.

compatibility

Compatibility wrapper classes are provided in compat/ that allow the classes to be called using the 1.x api. Simply move both the compatibility and current release source files into the directory your application expects to find the library.

downloads

version:	release date:	changelog:	download:
2.0.0	2010-01-28	view	zip (18Kb)
1.3.0	2008-11-01	view	zip (14Kb)
1.2.0	2007-12-25	view	zip (13Kb)
1.1.0	2007-12-05	view	zip (14Kb)
1.0.2	2006-02-07	view	zip (11Kb)
1.0.1	2006-01-23	view	zip (11Kb)
1.0.0	2006-01-19	view	zip (11Kb)
changelog

version 2.0.0 - 2010-01-28:
Significant refactor of the internals and a change in the API (a wrapper class is packaged in compat/content_negotiation.inc.php that provides the same API as found in 1.3, simply have it and conNeg.inc.php in the directory your application expects to find the library).
Application type data can now be provided in the form of a string conforming to the syntax and semantics of the relevent header field in the HTTP/1.1 specification, section 14 (rfc2616 http://www.ietf.org/rfc/rfc2616.txt).
The library now handles the accept-extension fragment in the Accept header.
The library now handles mediatypes that contain numeric characters in the subtype - thanks again go to richard (http://code.google.com/u/@VhBSQ1BRBxZDVgB7/) for this bugfix.
By default the generated datastructure is now sorted by the product of the application and user agent q factors when the application provides them.
version 1.3.0 - 2008-11-01:
The main generic_negotiation function has been significantly refactored to simplify the algorithms implementation and generally handle things more gracefully.
Negotiation performed on headers without providing a list of types to look for no longer returns wildcard types.
Negotiation performed on the charset, language and encoding headers ( through the charset_*, language_* & encoding_* functions) now supports wildcards.
Handling of how the user agent and application quality factors are used to determine the preferred type has been revised. The library now has a second mode where it sums the user agent and application quality factors and uses this value to determine the preferred resource. This behavior can be enabled by appending true as a second paramater to any of the public functions. Thanks to richard (http://code.google.com/u/@VhBSQ1BRBxZDVgB7/) for this suggestion.
version 1.2.0 - 2007-12-25:
Support for php 4.x dropped being as the php developers will no longer be supporting php 4 as of the 31st December.
Support for wildcard rules implemented.
No longer requires a list of types to look for, if there is no parameter passed to the negotiation functions then they generate a list internally from the browser's headers.
Fixed the XHTML & HTML negotiation class so that it works as intended.
version 1.1.0 - 2006-12-05:
Significant re-write to encapsulate functionality within a class.
There are now two versions, a version targetted at the PHP 4.x releases and a version targetted at the 5.x releases that takes advantage of the improved support for OOP techniques.
There is now a seperate include file that can be used to determine if a browser can handle XHTML, and if it can whether it has a preferance towards it or HTML.
version 1.0.2 - 2006-02-07:
Replaced the inner for loop and conditional with the use of the array_search function - my thanks go to NeoThermic for telling me about this function.
version 1.0.1 - 2006-01-23:
Added strtolower into parsing so that comparisons of media-types can be done with the '===' php identical operator without worrying about case.
version 1.0.0 - 2006-01-19:
Initial public release.
feedback

If you find any errors in this article, or any problems (bugs etc) with resources that are provided as part of it please provide feedback and I will try to address the issue promtly.