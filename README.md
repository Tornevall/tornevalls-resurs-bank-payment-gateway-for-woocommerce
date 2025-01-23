# Resurs Bank Payments for WooCommerce

This is a payment gateway for WooCommerce and WordPress. Do not run this plugin side by side with the prior releases of
Resurs Bank plugins!
Requires PHP 8.1

This is a payment gateway for WooCommerce and WordPress.

# System requirements

| Requirement        | Version  | Notes                                                                                                                                                    |
|--------------------|----------|----------------------------------------------------------------------------------------------------------------------------------------------------------|
| PHP                | 8.1+     |                                                                                                                                                          |
| WordPress          | Latest   | See the official PHP/WordPress compatiblity chart [here](https://make.wordpress.org/core/handbook/references/php-compatibility-and-wordpress-versions/). |
| WooCommerce        | 7.6.0+   |
| PHP CURL extension | Required |
| SSL (HTTPS)        | Required | Callbacks from Resurs Bank will not work without this.                                                                                                   |

# Configuration

Configuration are made through the admin panel.

# FAQ

### Where can I get more information about this plugin?

Find out more in about the
plugin [in our documentation](https://developers.resurs.com/platform-plugins/woocommerce/resurs-merchant-api-2.0-for-woocommerce/).

[Sign up for Resurs](https://www.resursbank.se/betallosningar).

### Can I upgrade from version 2.2.x?

No. Running this plugin means you have to remove the prior package.

# Developer notes

The following chapters are for developers only. They explain various concepts in
WordPress, WooCommerce and this module that are useful to know when developing
for this module.

## Blocks vs. legacy

**WordPress blocks** is a modern way to create content in WordPress. In legacy,
you would create a page with whatever content you could enter through the WYSIWYG
editor in the admin panel. You could also make use of shortcodes to add more
complex content (like the WooCommerce checkout).

Blocks on the other hand are re-usable components for the new Gutenberg editor.
They allow users to build complex layouts and content structures in a modular
and reusable way. Below is an explanation of how blocks work.

**Beware of the fact that not all themes you have installed fully support blocks.
The basic behavior of our plugin often relates to core functions in WooCommerce
and/or the themes. If the theme does not support blocks, you also need to ensure
that this is not the main problem.**

### Reusable Components with Attributes

Blocks are reusable components that can have various attributes to configure
their behavior and appearance. Attributes are properties that define the block's
data and settings. For example, an image block might have attributes for the
image URL, alt text, and caption.

### Rendering in Admin and Frontend

Blocks are rendered in both the admin (editor) and the frontend using
JavaScript, typically with React. In the admin, blocks provide a user-friendly
interface for content creation, while on the frontend, they render the final
HTML output.

### Example: Image and Paragraph Blocks

#### Image Block

- **Attributes**: `url`, `alt`, `caption`
- **Admin Rendering**: Provides an interface to upload/select an image, enter
  alt text, and add a caption.
- **Frontend Rendering**: Outputs an `<img>` tag with the specified attributes.

#### Paragraph Block

- **Attributes**: `content`, `align`
- **Admin Rendering**: Provides a text area to enter and format text.
- **Frontend Rendering**: Outputs a `<p>` tag with the specified content and
  alignment.

## NodeJs & NPM

Node.js is a JavaScript runtime built on Chrome's V8 JavaScript engine. It
allows developers to run JavaScript on the server side (without a browser).

NPM (Node Package Manager) is a package manager for Node.js. It helps developers
to install, share, and manage dependencies (libraries and tools) required for
their Node.js projects. NPM also provides a registry where developers can
publish and discover reusable code packages.

## TypeScript, JSX and TSX

TypeScript is a statically typed superset (extension) of JavaScript that adds
optional type checking and other features to the language. It helps developers
catch errors early and write more maintainable code.

JSX (JavaScript XML) is a syntax extension for JavaScript that allows you to
write HTML-like code within JavaScript. It is commonly used with React to
describe the UI structure. JSX makes it easier to create and visualize the
structure of React components by combining HTML and JavaScript logic in a
single file. During the build process, JSX is transpiled into regular
JavaScript.

- **.ts file**: A TypeScript file that contains TypeScript code. It is used for
  writing standard TypeScript code without JSX.
- **.tsx file**: A TypeScript file that contains JSX (JavaScript XML) code. It
  is used when working with React components, allowing you to write JSX syntax
  within TypeScript.

## Webpack and TS Loader

### Webpack

Webpack is a popular module bundler for JavaScript applications. It takes
modules with dependencies and generates static assets representing those
modules.

It can bundle various assets, such as JavaScript, CSS, images, and fonts, into
a single or multiple output files. Webpack uses a dependency graph to understand
how modules relate to each other and bundles them accordingly.

It can use loaders, such as `ts-loader` for TypeScript, to transform files into
modules that can be included in the bundle. Webpack also supports plugins to
extend its functionality, such as optimizing bundles, managing assets, and
integrating with other tools.

## Our Node.js and webpack configuration

Webpack is a node module, just like any other. It's described as a requirement
for our module through the `package.json` file. When we run `npm install` in the
root directory of our module, it will install all the dependencies listed in
`package.json`, including webpack.

### Webpack Configuration Summary

The `webpack.config.js` file is configured to bundle and transpile TypeScript
files for the project. Here is a summary of our configuration:

- **Dependencies**:
    - `@wordpress/scripts/config/webpack.config`: Base configuration from
      WordPress scripts.
    - `@woocommerce/dependency-extraction-webpack-plugin`: Plugin to handle
      WooCommerce dependencies.
    - `path`: Node.js module for handling file paths.

- **WooCommerce Dependency Mapping**:
    - `wcDepMap` and `wcHandleMap` are used to map WooCommerce dependencies to
      external resources.

WooCommerce initially used separate node modules, but they have been merged into
the main WooCommerce repository. The reason is that if every module was to
specify their own requirements it would cause a lot of overhead. For example,
imagine a scenario where ten different WordPress modules specified a dependency
on the same version of **@woocommerce/block-data**. This would mean that each
module would bundle their own version of **@woocommerce/block-data**,
effectively leading to the same code being bundled multiple times, causing a
decrease in performance for the website.

To avoid this, WooCommerce decided to bundle all of the node modules directly
into the WooCommerce repository. So what version of **@woocommerce/block-data**
is available to our module depends on the version of WooCommerce that is being
used.

This is a good way to enhance performance, but it may cause compatibility
issues since we cannot specify our own package requirements. This means we need
to be vigilant in our testing, ensuring new versions of WooCommerce do not
break our compatibility.

The bundled node modules are made available to us in our browser environment
on the global `window` object. This is where the
`WooCommerceDependencyExtractionWebpackPlugin` comes in. This plugin lets us
tell webpack that specific dependencies are available on the global `window`
object. For example this:

```typescript
const wcDepMap = {
    '@woocommerce/blocks-registry': ['wc', 'wcBlocksRegistry'],
    '@woocommerce/settings': ['wc', 'wcSettings'],
    '@woocommerce/block-data': ['wc', 'wcBlocksData'],
};
```

Maps the dependencies `@woocommerce/blocks-registry`, `@woocommerce/settings`
and`@woocommerce/block-data` to the global `window` object. This means that we
can use import statements for these in our TypeScript files, and webpack will
know that they are available on the global `window` object. When JS files are
transpiled from our TS files, webpack will know that these dependencies are
external resources and will not bundle them into our output files.

```typescript
import * as WcBlocksData from '@woocommerce/block-data';
```

Will use the object `window.wc.wcBlocksData` as the import.

It is essential to understand how this works in order to develop features for
WooCommerce. Since the node modules are bundled directly into WooCommerce, you
will need to visit their github repository to see the raw source files:
https://github.com/woocommerce/woocommerce/tree/trunk/plugins/woocommerce-blocks/assets/js

- **Entry Points**:
    - `dist/gateway`: Entry point for `src/Modules/Gateway/resources/ts/gateway.tsx`.
    - `dist/update-address`: Entry point for `src/Modules/GetAddress/resources/ts/update-address.ts`.

- **Output**:
    - Bundled files are output to the `assets/js` directory with filenames
      matching the entry point keys.

Basically, `src/Modules/Gateway/resources/ts/gateway.tsx` will be bundled into
`assets/js/dist/gateway.js` and so on.

- **Plugins**:
    - Filters out the default `DependencyExtractionWebpackPlugin`.
    - Adds `WooCommerceDependencyExtractionWebpackPlugin` with custom
      `requestToExternal` and `requestToHandle` functions.

Basically, we disable any default `DependencyExtractionWebpackPlugin` to avoid
conflicts and then register the `WooCommerceDependencyExtractionWebpackPlugin`
with custom functions to resolve WooCommerce dependencies from the global
`window` object.

### Transpiling Process

1. **Entry Points**: Webpack starts from the specified entry points
   (`gateway.tsx` and `update-address.ts`).
2. **Loaders**: Uses `ts-loader` to transpile (translate) TypeScript code to
   JavaScript.
3. **Output**: Transpiled files are output to `assets/js/dist` with the
   filenames `gateway.js` and `update-address.js`.

The transpiling process is powerful in that it lets us write safe TS code and
convert this into ES5 code which can essentially be run by any browser in the
world.

## React and Redux

### React

React is a JavaScript library for building user interfaces. It allows developers
to create reusable UI components that manage their own state.

In short it allows you to attach an HTML element to a JavaScript object, and
when the object is manipulated the HTML will automatically update.

Think of like this:

```javascript
var store = {
    my_label: 'Hello, world!',
};

function setLabel(newLabel) {
    store.my_label = newLabel;
}
```

```html

<div>{store.my_label}</div>
```

React would automatically update the HTML when `store.my_label` is updated by
calling `setLabel('Hello, React!')`.

This is a major simplification of how React works, but it gives you an idea of
how it can be used.

### Redux

Redux is a predictable state container for JavaScript applications, often used
with React. It helps manage the state of an application in a consistent way.

Think of this like the object `store` in the previous example. Redux is the
object containing our data, and a bunch of functions to handle this data and
related events.

Again, major simplification made to illustrate how it works.

## How to build new assets

First, move to the base directory of the plugin. Then run the following command:

```bash
npm install # This will install all the dependencies, only run this once.
npm run build # This will build the assets.
```

The build command will read the **ts** and **tsx** files defined in our webpack
configuration and transpile them into **js** files. These files will be placed
in the `assets/js/dist` directory.

You will notice that alongside the **js** files, there are also **php** files,
named the same as the **js** files but suffixed with **.asset.php**. These
files contain metadata about the **js** files, such as their dependencies. For
example, at the time of writing this, the content of `gateway.asset.php` is:

```php
<?php return array('dependencies' => array('react', 'wc-blocks-data-store', 'wc-blocks-registry', 'wc-settings', 'wp-data'), 'version' => '1e39224cc52fda550804');
```

When using blocks, WordPress will automatically load the dependencies specified
in these files. We can also do so manually though when queuing scripts in our
PHP files:

```php
wp_enqueue_script('gateway', plugins_url('assets/js/dist/gateway.js', __FILE__), array('react', 'wc-blocks-data-store', 'wc-blocks-registry', 'wc-settings', 'wp-data'), '1e39224cc52fda550804', true);
```

Its crucial for the loading order that we specify the dependencies.

## How to automatically build assets as files changes

If you are developing a new feature in a TS file you can run the following
command to automatically build the assets as you save the file:

```bash
npm run start
```

## How to format the code

To format the code according to the WordPress coding standards, run the following
command:

```bash
npm run format
```

## Get Address

The Get Address implementation differs slightly between legacy and blocks. How
we inject the HTML for the widget differs, as well as the JavaScript that is
responsible for the widget's functionality.

If we look at `\Resursbank\Woocommerce\Modules\GetAddress\GetAddress::init()` we
will see three filters being added:

* `wp_enqueue_scripts` - This loads the same JS and CSS code regardless of what
  checkout is being used. Reason is because there is no actual way to know
  whether we are on the checkout page, and whether that checkout page is using
  blocks or legacy since WordPress resources are loaded site-wide.
* `the_content` - This is where we inject the HTML for the widget when using the
  blocks based checkout. There is no specific hook we can use, instead we will
  scan the page content for a blocks tag we know must exist on the blocks based
  checkout page and then inject our widget through a preg_replace.
* `woocommerce_before_checkout_form` - This is where we inject the HTML for the
  widget when using the legacy checkout. The hook is specific to legacy.

The JavaScript that is responsible for the widget's functionality is located in
`src/Modules/GetAddress/resources/ts/update-address.ts`.

This file loads both `./update-address/legacy.ts` and `./update-address/blocks.ts`
and will execute one of them based on what checkout is being used. We attempt to
determine this by examining the DOM for elements specific to the blocks / legacy
checkout.

We've coded this as TS files because they contain no HTML elements (i.e. no
JSX code).

Note that the CSS is written using **SCSS**
(`src/Modules/GetAddress/resources/css/custom.scss`). Compilation to CSS should
be configured using webpack, but currently there is no such configuration and
you will need to manually setup a listener to transpile the SCSS to CSS. You
can for example install the sass ruby gem or whatever else you prefer. The CSS
file this will generate is `src/Modules/GetAddress/resources/css/custom.css`

## Gateway in blocks

The blocks based WooCommerce checkout expects a block component to be
registered for each available payment method.

This causes problems for us, since our payment methods are dynamic, we have no
knowledge of them before code execution.

Payment methods can however be passed information from PHP, and we leverage this
by first registering a single payment method called `resursbank` and passing it
data which is assembled from all our payment methods.

Open `\Resursbank\Woocommerce\Modules\Gateway\GatewayBlocks`. You will see that
we use the `woocommerce_blocks_payment_method_type_registration` filter to
register our payment method (`$this->name` specifies "resursbank").

`\Resursbank\Woocommerce\Modules\Gateway\GatewayBlocks::get_payment_method_data()`
is responsible for assembling the data that is passed to the block component.
We supply an array consisting of `allowed_country`, to filter methods based on
configured address country, and `payment_methods`, a list of all payment methods
and their relevant configuration.

The frontend integration exists in `src/Modules/Gateway/resources/ts/gateway.tsx`.
As you can see around **line 21** we do `settings.payment_methods.forEach` to
loop through the list of payment methods, registering each of them as a
separate payment method block component (see the call to
**registerPaymentMethod** around **line 104**).

Note that this file is suffixed with **.tsx** because it contains JSX code.

Also note the **canMakePayment** property of the object we return. This is a
native property of payment methods, a function which executes automatically when
the cart store (more on this later) changes, to check whether payment methods
are available.

Also note that the **edit** property of the object returned is required for our
payment methods to render, even if we do not intend for our payment method
blocks to be modifiable.

On line 106 - 108 we supply the HTML components for our payment method:

* \<Label> - This corresponds to the **const Label** declared around
  **line 98**
* \<Content> - This corresponds to the **const Content** declared around
  **line 60**

If you examine these constants, you will see that they both declare a function
which returns a JSX element. These are re-rendered when you toggle between
payment methods, or when specific store state changes (this is a little bit of a
guess, it could be that it updates when _any_ store changes, but this is a
little unlikely since it would introduce a serious performance decrease and
cause confusion around the state being split into multiple stores at all).

For example, see the **Content** const where we update the iframe URL around
**line 69** to reflect the current cart total (taking applied coupons etc. into
account).

## Payment methods and customer tracking

Depending on whether the fetchAddress widget is enabled or not, the rendering behavior of payment methods varies. A
persistent challenge since the creation of this part of the plugin has been the necessity to identify the customer type
regardless of the widget's state.

To simplify this process, we have implemented a customer-tracking feature in the segments actively utilized by our
widget. The tracking itself is located in a dedicated TypeScript
segment (`src/Modules/GetAddress/resources/ts/update-address/customer.ts`). This implementation consists solely of an
AJAX request, resembling the approach previously used in the legacy system without blocks.

When the fetchAddress functionality is invoked, it is essential to load the scripts even if the widget is disabled. This
is because our payment methods, when rendered, support both business customers (LEGAL) and private individuals (
NATURAL). However, our block handler must always be aware of the available payment methods. One reason for this
requirement is that switching between NATURAL and LEGAL filters out payment methods in a way that prevents them from
being retrieved again without a page refresh.

We have resolved this by storing all payment methods in memory. During each update of the cart and WooCommerce data,
payment methods are retrieved from memory rather than relying on WooCommerce's partially provided data. This ensures
consistent availability and proper rendering of payment options regardless of customer type or widget state.

It is also in this segmentation that we handle customer types. Without the customer-type tracking, our backend scripts
would be unable to determine which payment methods should be displayed in different scenarios, resulting in NATURAL
always being prioritized. This, in turn, leads to LEGAL methods sometimes not being properly registered or displayed in
the checkout unless we ensure that payment methods can be programmatically updated afterward. When everything functions
as intended, all payment methods will always appear immediately upon loading, even when LEGAL is used. This behavior is
managed through `WcSession`, where `customerType` is set, for instance, when the 'company-name' field is filled in the
customer details. This occurs via a backend call while the script is executing.

To prevent issues with this kind of loading, we use the following two methods to ensure that all methods are available,
even when the session for which customerTypes reside in, is not yet synchronized:

```typescript
this.loadAllPaymentMethods();
this.refreshPaymentMethods();
```

## State and Store

### State in JavaScript

State in JavaScript refers to the data that an application or component manages
and uses to render the UI. It can be stored in variables, objects, or arrays and
is typically managed within the component itself.

### Stores in Redux

Redux is a state management library that provides a single source of truth for
the state of an entire application. The state is stored in a single store,
which is an object tree.

In Redux, the state can be separated into multiple parts using **reducers**.
Each reducer manages a slice of the state.

### In WordPress

For example, cart data in WooCommerce is handled by the **cart** store. See the
following lines in **gateway.tsx** for an example of how we use this:

* 5: importing the store name from `@woocommerce/block-data`
* 61: using **select** to get the cart data from the store.

You could likewise use **dispatch** to update the cart data, importable from
`@wordpress/data`, just like **select**.
