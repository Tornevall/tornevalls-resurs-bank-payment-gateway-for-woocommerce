# Resurs Bank Resurs Checkout: ResursCheckoutJS #

## Introduction ##

### Maintenance Information

This version of ResursBankJS is about to be deprecated. There are new versions coming, which works completely different
compared to this release. Assure yourself that your system is compatible with this, on migration.

For questions, contact us at [onboarding@resurs.se](onboarding@resurs.se)

### The Problem (Moment 22)

To create an order at Resurs Bank, Resurs Bank requires an order id - preferably one returned from your ecommerce platform.
Sometimes store platforms can't return the order id before the order has actually been created in the store platform.
This might be a problem, since creating an order lite this, won't give very much information about the customer itself.

Resurs Checkout has a communication interface that allows a web store to pick up data from the checkout-iframe and place
the order as soon as the customer has been confirmed "OK" at Resurs Bank. However, the store still needs to return a
valid order id. To get around this problem, your store plugin may create a temporary order id first, that it use to
initialize a payment at Resurs Bank. As the communication interface in the checkout allows background activities,
it is also possible to create the order as soon as the customer confirms the payment at checkout. At this moment, the
backend plugin has the chance to synchronize the proper order id, created by the store, with the temporary initiated order id at Resurs Bank. 

ResursCheckoutJS is a small framework-like script, that handles the primary part of those issues. ResursCheckoutJS itself has
*no external dependencies* like for example jQuery, since we want to be sure that the script fits in any web store and
plugin you decide to use it in. The script is written so that you can put it on a webpage without having it primarily
activated (to avoid colliding with other scripts). It utilizes the message handler in the Resurs Checkout iframe, so
that you can push an order into the store in the background, as the checkout is completed at Resurs Bank.

Communicating with the iframe is however not required in any matter, unless you are planning to book the order as
describe above, *before* the booking is made at Resurs Bank. This means that you actually can live completely without this interactivity.

## Requirements ##

* A pre-set js-variable: `RESURSCHECKOUT_IFRAME_URL` (_`OMNICHECKOUT_IFRAME_URL` are kept for backwards compatibility_) needs to be set from your store, to define where events are sent from.
* Make sure that shopUrl is sent and matches with the target iframe URL, when creating the iframe at API level.
* A html element, with an id, that holds the iframe (default id: resurs-checkout-container)

## Dependencies ##

* None

## Documentation ##

Complete documentation can be found at [https://test.resurs.com/docs/x/5ABV](https://test.resurs.com/docs/x/5ABV)


