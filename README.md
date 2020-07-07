# RBWC

This is a plugin written for WooCommerce and WordPress. It follows that standards (as much as possible) of WooCommerce. This means that if you do not upgrade your WooCommerce plugin from time to time, the plugin for Resurs Bank may become obsolete also.

If you read this text from within the resurs-bank-payment-gateway-for-woocommerce, you should know that the codebase and README content is not entirely the original content.
If you read this text from [this bitbucket repo](https://bitbucket.tornevall.net/projects/WWW/repos/tornevall-networks-resurs-bank-payment-gateway-for-woocommerce), consider it the original base as of july 2020. This might change in future also.
This plugin will however give you a wider support for filters and actions to simplify the "pluggables". There is also an older version alive here [here](https://bitbucket.tornevall.net/projects/WWW/repos/tornevall-networks-resurs-bank-payment-gateway-for-woocommerce/browse/init.php?at=refs%2Fheads%2Fobsolete%2Fv1-old) that was intended to the first new version. This is also reverted.
 
## Requirements and security considerations

* WooCommerce 3.4.0 or higher.
* Do not run anything lower than [PHP 5.6.20](https://wordpress.org/news/2019/04/minimum-php-version-update/).
* Version 2.x had some flaws, at least one of the to consider quite severe; the payment methods was written as file libraries and the directory structure has to be writable. The imports of those methods was also written dynamically, meaning the directory structure was [globbed](https://www.php.net/manual/en/function.glob.php) into the runtime. If an attacker was aware of this (which is possible by reading the code), arbitrary files could be written into this structure and get executed by the plugin. For this release, all such elements are removed.
* [WordPress PHP recommendation](https://meta.trac.wordpress.org/ticket/5257) is raised to 7.2 - you should upgrade too. [You can also read more here](https://wpastra.com/changing-wordpress-php-version/).

## Configuring

WooCommerce has a quite static configuration setup that makes it hard to create new options.

## Importing to Resurs Bank Repo

If the above text wasn't read from Resurs Bank repo and the intentions is/has been to import the content from "RBWC" into Resurs Bank - have no worries. Most of the code is written with those intentions. It should therefore be possible to just copy/paste the structure. Just make sure that the old base is removed before doing this.

### Other Considerations

You should also take a look at a few other things too, that are listed below.
 
#### composer.json
 
The package namespace is specifically pointing to  `tornevall/resurs-bank-payment-gateway-for-woocommerce` which you probably want to change. Also take an important note that the branches may get desynched if different developing is being made in the repos. Once you decide to synchronize, you could just add another remote to your forked gitrepo and synch them.

#### readme.txt

The readme.txt contains another head title. Change it.


# Notes to self

* json-configurables instead of static content
