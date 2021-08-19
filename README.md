# RBWC

This is a plugin written for WooCommerce and WordPress. It is no longer a fork of the [prior repository that can be found at Resurs Bank](https://bitbucket.org/resursbankplugins/resurs-bank-payment-gateway-for-woocommerce) since much of that codebase has been impossible to reuse.

## Documents and links

[Documentation is for the moment located here](https://docs.tornevall.net/display/TORNEVALL/RBWC+Payment+Gateway).
[The official bitbucket repo as of v0.0](https://bitbucket.tornevall.net/projects/WWW/repos/tornevall-networks-resurs-bank-payment-gateway-for-woocommerce).
[Github Synchronized repository](https://github.com/Tornevall/wpwc-resurs) will probably be the new official repository as bitbucket server will shut down in a few years from now.

## Disclaimer

There is breaking changes in this plugin if you tend to re-use it as it was the prior version (2.x).
This is a standalone edition that - if you import it straight up as it was the older release - it definitely *could* break, not your site, but the recent setup.
However, there are bridging over from the old version to the new version even if most of the old filters has dropped developer support.
The external repo at [github](https://github.com/Tornevall/wpwc-resurs) and [bitbucket](https://bitbucket.tornevall.net/projects/WWW/repos/tornevall-networks-resurs-bank-payment-gateway-for-woocommerce) is not itself supported by Resurs Bank, but the intentions with this plugin is to eventually import it as a "next major version" update so it could be officially supported there.

If you read this text from within the resurs-bank-payment-gateway-for-woocommerce, you should know that the codebase and README content is not entirely the original content. If this has happened, the project is importend and forked out to a Resurs supported release.
If you read this text from some of the mentioned repos above, you can consider it the original codebase as of july 2020, when the project was initialized.

### What has changed?

This plugin do have much wider support for filters and actions to simplify work for developers that need to connect their own features to whatever they need to do.
It has also covered some serious security issues in the prior release.
The fact that this is a standalone release of a payment gateway, it also gives the opportunity to move around freely in other codebases and API usages. For example, we could make use of an external URL link checker to verify whether your site is reachable from the outside or not.
As mentioned above, in the first section, there might be several breaking changes compared with Resurs version 2.x

## Requirements and security considerations

* PHP: [Take a look here](https://docs.woocommerce.com/document/server-requirements/) to keep up with support. As of aug 2021, both WooCommerce and WordPress is about to jump into 7.4 and higher. Also, [read here](https://wordpress.org/news/2019/04/minimum-php-version-update/) for information about lower versions of PHP.
* WooCommerce: v3.4.0 or higher (old features are ditched) and the actual support is set much higher.
* Do not run anything lower than [PHP 5.6.20]().

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
