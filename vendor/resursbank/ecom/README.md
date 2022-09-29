# ECom V2

Library to communicate with Resurs Bank APIs. This library intends to implement
complete API coverage.

---

## Config

The **Config::setup()** must always be called before performing any API call.
This method creates a configured instance of the ECom library with all necessary
information to execute API calls and perform related actions (such as logging, 
caching, data persistence etc.). **Config** acts as a singleton and the instance
is stored in **Config::$instance**.

This is a little unorthodox, but it allows for a single point of configuration
for the library. This allows for simpler integrations. For example, if you
utilize ECom from various places within your application you could call
**Config::setup()** at a central point early in your application lifecycle,
making sure that any calls to the library are configured properly always,
regardless of where you may require the library. This can also have a beneficial
impact on dependency management.

## Exceptions

### Exceptions

Custom Exception classes.

* ApiException | Base exception for all API exceptions.
* AuthException | Exception for authentication errors.
* CacheException | Exception for cache errors.
* CurlException | Exception for curl errors.
* EventException | Exception for event errors.
* EventSubscriberException | Exception for event subscriber errors.
* FilesystemError | Exception for filesystem errors.
* IOException | Exception for IO errors.
* TestException | Exception for test errors.
* ValidationException | Exception for validation errors, see below.

### Exceptions/Validation

Custom Exception classes utilized for data validation.

**NOTE:** All of these exceptions are subclasses of ValidationException.

* EmptyValueException | Value was not expected to be empty.
* FormatException | Value was not in expected format.
* IllegalCharsetException | Value contained illegal characters.
* IllegalTypeException | Value was not of expected type.
* IllegalValueException | Value was not allowed.
* MissingKeyException | Required key was not found in array.

---

## Libraries

Libraries are located under **src/Lib**. Libraries are allowed to communicate
with each other. As such they are allowed to have dependencies on each other.
These classes contain abstract business logic and are not meant to be called
directly by the end user. Libraries are not allowed to communicate with modules,
modules are however allowed to communicate with libraries.

### Api

General API classes and functionality.

* Mapi | Contains centralized business logic for communication with Merchant API.

### Cache

* AbstractCache | Abstract base class for all cache implementations.
* CacheInterface | Interface for all cache implementations.
* Filesystem | Filesystem cache implementation.
* None | No cache implementation. This is the default cache implementation.
* Redis | Redis cache implementation.

### Collection

* Collection | Abstract base class for all collection implementations.

### DataStorage

Work in progress. Intended to be a persistent data storage implementation.

### Event

Work in progress. Intended to be an event system to communicate between Modules.

### Locale

Work in progress. Intended to help with localization and country availability.

### Log

Logging functionality.

* FileLogger | File logger implementation.
* LoggerInterface | Interface for all logger implementations.
* LogLevel | Log level enumeration.
* StdoutLogger | Stdout logger implementation.

### Model

Data object implementations.

* Model | Abstract base class for all model implementations.

### Network

Network communication functionality.

* ApiType | API type enumeration.
* AuthType | Authentication type enumeration.
* ContentType | Content type enumeration.
* Curl | Curl implementation.
* RequestMethod | Request method enumeration.
* Url | URL implementation.
* Curl/Header | Helper methods for Curl headers.
* Model/Auth/Basic | Basic authentication model.
* Model/Auth/Jwt | JWT authentication model.
* Model/Header | Header model.
* Model/JwtToken | JWT token model.
* Model/Response | Generic response model. This is the expected return type of all API calls.

### Simplified

Data and logic specifically related to the Simplified API.

* Config | Configuration object for Simplified API.

### Utilities

Generic functionality that does not belong to any specific library.

* Generic | Methods to extract Composer and Docblock information.
* DataConverter | Helps us convert anonymous arrays to known objects. Also lets us convert multidimensional arrays to collections.
* DataConverter/TestClasses | Test classes for DataConverter.

### Validation

Functionality to help us validate various kinds of data. Classes are separated by the data type they validate.

* ArrayValidation | Validation for arrays.
* BoolValidation | Validation for booleans.
* FloatValidation | Validation for floats.
* IntValidation | Validation for integers.
* StringValidation | Validation for strings.

## Modules

Modules are independent pieces of functionality that are meant to be used by
the end user. Modules are not allowed to communicate with each other to allow
for maximum flexibility.

### Annuity

Integration of **annuity factors**, currently incomplete. At present this only
reflect how we could leverage the **Event** library to implement logic that
will fetch annuity factor information from the API when payment methods are
being fetched (fetching factors for each method fetched from the API). Since
modules are not allowed to have knowledge of each other, this is the best way
to achieve this.

### PaymentMethod

Integration of **payment methods**, currently incomplete. Work in progress.

### RCO

Implementation of the Checkout API (iframe based checkout).

* Repository | Repository for RCO. 
* Api/GetPayment::call() | Fetch payment information from the API.
* Api/InitPayment::call() | Initialize payment session (iframe) with the API.
* Api/UpdatePayment::call() | Update payment session in the API.
* Api/UpdatePaymentReference::call() | Update payment reference in the API.
* Model/* | Model classes for API requests and responses.

Please note that all API calls should be performed through **Repository**.

When using the RCO you first need to call **InitPayment** to initialize
the payment session. You will be required to provide a reference for this
session which should be your order number if you already have that on hand at
this point. You will otherwise be able to update this value later using
**UpdatePaymentReference**. **InitPayment** Will supply you with the iframe to
allow checkout. **UpdatePayment** Allows you to update the payment session with
new items etc. after the payment session has already been created, so you do not
need to re-create the session every time the cart changes for example. Whenever
the totals in your platform change you should call this endpoint to update the
payment session which will reflect the new total within the iframe through
JS sockets. You can call **GetPayment** to fetch the payment session
information at any time. When the client completes their purchase the session
will be converted to an actual payment.

### RCO Callback

Documentation TBD.

### Store

Implementation of **stores** in the Merchant API (MAPI).

* Repository | Repository for Store.
* Api/GetStores::call() | Fetch list of available stores from the API.
* Model/* | Model classes for API requests and responses.

Please note that all API calls should be performed through **Repository**.

Every API account has one or more stores. You can fetch the list of available
stores through the **Repository** class. You will need your store(s) for
subsequent API calls to fetch payment methods for example. The **Repository**
class will return a **Collection** of **Store** objects read either from cache
or directly from the API.
