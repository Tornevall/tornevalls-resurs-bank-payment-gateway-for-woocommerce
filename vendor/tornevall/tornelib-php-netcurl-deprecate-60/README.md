# Helper library for deprecations

During development of netcurl-6.1 I realize how damaged v6.0 and how much non-PSR4 it actually is, I decided to push all prior abstractions in a deprecation library instead of destroying v6.1 with a bunch of disturbing files in its root path.

So here it is! The deprecation library for netcurl-6.0

## Why does it even exist

As v6.1 is a huge facelift, the library still has to be tested with applications that is using v6.0 - as this involves bigger projects, the new version has to be build with caution and compatibility. Probably this will be removed in future releases.

Until then, this piece of junk has to be here, mainly because I need to be able to test older applications with abstractions that no longer is.

## Usage:

To make v6.1 work in an environment that is based on the old abstract classes, add this to your composer package:

    "tornevall/tornevall/tornelib-php-netcurl-deprecate-60": "^6.0",

Good luck!
