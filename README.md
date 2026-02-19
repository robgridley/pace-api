# Pace API Client

An unofficial PHP client library for EFI Pace's SOAP API, created by a Pace administator and PHP developer. This library makes make some assumptions about convention to make your life just a little bit easier.

## Installation

Install via [Composer](http://getcomposer.org/):

```
$ composer require robgridley/pace-api
```

PHP 8.1+ with the SOAP, SimpleXML and Fileinfo extensions required.

## Testing

PHPUnit tests with 100% code coverage for `Model`, `KeyCollection` and `XPath\Builder` classes.

To run the tests:

```
$ composer test
```

## To-dos

* Write tests for remaining classes
* Implement remaining Pace services (e.g. "InvokeProcess")

## Configuration

You'll need to create a Pace system user with "Allow remote API usage" enabled.

```php
use Pace\Client as Pace;
use Pace\Soap\Factory as SoapFactory;

$pace = new Pace(new SoapFactory(), 'epace-staging.domain.com', 'apiuser', 'apipassword');
```

## CRUDD

### Creating an object

To create a new object in Pace, get a model from the Pace client, set its properties, then call the `save()` method. You can retrieve a model instance by calling the `model()` method or using a dynamic property.

```php
$csr = $pace->model('CSR');
// or the shorter, prettier:
$csr = $pace->csr;

$csr->name = 'John Smith';
$csr->email = 'jsmith@domain.com';
$csr->save();
```

### Reading an object

You can read an object by its primary key, which will return a single model.

```php
$csr = $pace->csr->read(1);

echo $csr->email; // prints "jsmith@domain.com"
```

Compound keys are separated by a colon.

```php
$jobPart = $pace->jobPart->read('12345:01');
```

### Updating an object

In addition to creating objects, the `save()` method is used to update existing objects.

```php
$csr = $pace->csr->read(1);

$csr->active = false;
$csr->save();
```

### Duplicating an object

To duplicate an existing object, first load an existing object, optionally modify its properties, then call the `duplicate()` method. A new model instance will be returned and the existing model will be restored to its original state.

The `duplicate()` method accepts one optional argument: a new primary key value. If you do not supply a primary key, Pace will automatically increment in most cases.

```php
$csr = $pace->csr->read(1);
$csr->name = 'Jane Smith';
$newCsr = $csr->duplicate();

echo $csr->name; // prints "John Smith"
echo $newCsr->name; // prints "Jane Smith"
```

### Deleting an object

The `delete()` method accepts one optional argument: the name of the primary key for the model. If none is provided, it will be guessed.

```php
$csr = $pace->csr->read(1);
$csr->delete();
```

## Finding objects

### Basics

Pace's web service uses XPath expressions to find objects. The included `XPath\Builder` class takes care of converting PHP native types and makes your filters more readable (in a PHP editor, anyway).

#### Finding mutiple objects

```php
$jobs = $pace->job
	->filter('adminStatus/@openJob', true)
	->filter('@jobType', 1)
	->find();
```

The above returns a collection of models.

#### Finding a single object

```php
$csr = $pace->csr->filter('@name', 'Jane Smith')->first();
```

The above returns a single model instance.

### Operators

All of the operators supported by Pace are supported by the client: `=`, `!=`, `<`, `>`, `<=`, `>=`. `startsWith()` and `contains()` are also supported.

```php
$millionPlus = $pace->salesPerson->filter('@annualQuota', '>=', 1000000)->find();

$coated = $pace->inventoryItem->contains('@description', 'C2S')->find();

$tango = $pace->inventoryItem->startsWith('@description', 'Tango')->find();
```

### Grouped filters

Sometimes you may need to group filters to create more complex conditions.

```php
$customers = $pace->customer
    ->filter('@state', 'ON')
    ->filter(function ($xpath) {
        $xpath->filter('@city', 'Toronto');
        $xpath->orFilter('@city', 'Ottawa');
    })
    ->find();
```

As you can see, passing a closure creates a nested set of conditions.

### Sorting

Use the `sort()` method to sort your results. The `sort()` method accepts two arguments: an XPath expression identifying the field to sort on and a boolean to determine the direction, which has a default value of false (ascending). You can chain as many sorts as needed, although I'm not sure if Pace has a limit.

```php
$jobs = $pace->job
	->filter('adminStatus/@openJob', true)
	->sort('customer/@custName')
	->sort('@job', true)
	->find();
```

### Limiting

Use the `offset()` and `limit()` methods to limit your results. The default offset is 0 if it is not specified.

```php
$jobs = $pace->job
	->filter('adminStatus/@openJob', true)
	->sort('@dateSetup', true)
	->limit(50)
	->find();
```

You can also use the `paginate()` method to set the offset and limit for a page.

```php
$jobs = $pace->job
	->filter('adminStatus/@openJob', true)
	->sort('@dateSetup', true)
	->paginate(1, 50)
	->find();
```

### Eager loading

The `load()` method preloads the models as part of the find request, using the find object aggregate service. It does not read the entire object; you must specify a list of fields in XPath. If the offset and limit are not specified, then 0 and 1,000 will be used by default.

```php
$employees = $pace->model('Employee')->filter('@status', 'A')->load([
    '@firstName',
    '@lastName',
    'department' => 'department/@description',
])->find();
```

## Dates

Dates are automatically converted to and from [Carbon](http://carbon.nesbot.com/) instances. Check out the `Soap\DateTimeMapper` and `Soap\Factory` classes if you want to see how this happens.

## Key Collections

The raw result of a find objects SOAP call is an array of primary keys. The model class automatically wraps that array in a KeyCollection object to provide you with a bunch of conveniences, and to prevent unnecessary calls to the read object service by only loading models as they're needed.

You can loop over a `KeyCollection` like an array. Each model will be loaded as you interate over it, so if for example you break out of the loop, the remaining models will never have been loaded.

```php
$estimates = $pace->estimate->filter('@entryDate', '>=', Carbon::yesterday())->find();

foreach ($estimates as $estimate) {
	if ($estimate->enteredBy == 'jsmith') {
		echo "John has entered an estimate since yesterday!"
		break;
	}
}
```

KeyCollection also has a number of useful methods such as `all()`, `paginate()` and `first()`.

## Relationships

### Loading related models

You can load related models automatically via dynamic methods.

For a "belongs to" relationship, the model property name and foreign model type must match. For example, the Customer object has a property named 'csr', which contains the primary key for the 'CSR' object type.

The following returns a single Model.

```php
$customer = $pace->customer->read('HOUSE');
$houseCsr = $customer->csr();
```

To load "has many" related models, call the camel-cased plural of the foreign model's type. It is assumed that the foreign model stores the parent model's primary key in a propery named after the parent model's type. For example, the 'Job' object stores the 'Customer' object's primary key in a property named 'customer'.

The following returns an `XPath\Builder` object. You may optionally add additional filters on the related models.

```php
$customer = $pace->customer->read('HOUSE');
$houseJobs = $customer->jobs()->filter('@adminStatus', 'O')->get();
```

If you find an object which flies in the face of convention, you can call the public `belongsTo()` and `hasMany()` methods directly.

The following two examples are the same.

```php
$notes = $customer->customerNotes()->get();
$notes = $customer->hasMany('CustomerNote', 'customer', 'id')->get();
```

In the first example, all of the required arguments are guessed. The second example explicitly provides them.

### Compound keys

Initial support for loading related models with compound keys has been added. Call the `hasMany()` or `belongsTo()` methods, passing a string containing the field names (which contain the keys) separated by colons.

```php
$jobPart = $pace->jobPart->read('12345:01');
$jobMaterials = $jobPart->hasMany('JobMaterial', 'job:jobPart')->get();
```

### Associating related models

If the model you're associating has a guessable primary key, you can assign the model as a property value. Otherwise, you'll need to explicitly assign the primary key value.

```php
$batch = $pace->inventoryBatch;
$batch->save(); // save the model to generate a primary key

$line = $pace->inventoryLine;
$line->inventoryBatch = $batch;
```

## Transactions

You can wrap your operations in a database transaction so that all calls may be rolled back in the event of an error. Using transactions has the added benefit of deferring any event handlers until all of your API calls are complete.

Note: The transaction service was introduced in Pace 29.0-1704.

### Using a closure

Use the `transaction()` method to execute your operations in a database transaction. Pace will rollback the transaction in the event of a server-side error, and the API client will automatically rollback the transaction if a PHP exception is thrown. If the closure executes successfully, and there are no server-side errors, the transaction will be committed.

```php
$pace->transaction(function () use ($pace) {
    $job = $pace->model('Job');
    $job->customer = 'HOUSE';
    $job->description = 'Test Order';
    $job->jobType = 10;
    $job->adminStatus = 'O';
    $job->save();

    $jobPart = $job->jobParts()->first();

    $jobMaterial = $pace->model('JobMaterial');
    $jobMaterial->job = $jobPart->job;
    $jobMaterial->jobPart = $jobPart->jobPart;
    $jobMaterial->inventoryItem = 'ABC123';
    $jobMaterial->plannedQuantity = 100;
    $jobMaterial->save();

    throw new Exception('Just kidding. Roll it back.');
});
```

### Using transactions manually

Alternatively, you can call the `startTransaction()`, `rollbackTransaction()` and `commitTransaction()` methods manually.

```php
$pace->startTransaction();

$csr = $pace->model('CSR');
$csr->name = 'Definitely Not Evil';
$csr->save();

if ($csr->id == 666) {
   // Oh no. They are evil!
   $pace->rollbackTransaction();
} else {
   $pace->commitTransaction();
}
```

## JSON

Both the `Model` and `KeyCollection` classes implement the `JsonSerializable` interface and casting either class to a string will generate JSON.

```php
// print a JSON representation of the House account
echo $pace->customer->read('HOUSE');
```

## Attachments

### Attaching files

To attach a file to a model, you only need to specify its name and content. The library takes care of guessing the MIME type and encoding the content.

```php
$job = $pace->model('Job')->read('12345');
$attachment = $job->attachFile('test.txt', file_get_contents('test.txt'));
$attachment->description = 'A test file';
$attachment->save();
```

The `attachFile()` method returns a FileAttachment model so you can set the category, description, etc. Only call the `save()` method if you change any attributes.

You can also attach a file to a field by specifying the name of the field. This is used throughout Pace for logos, photos, layouts, etc.

```php
$company = $pace->model('Company')->read('001');
$company->attachFile('logo.png', file_get_contents('logo.png'), 'logo');
```

### Retrieving attached files

Files attached to a model are retrieved via a special relationship method. It behaves the same as a "has many" relationship and returns an `XPath\Builder` instance.

```php
$attachments = $job->fileAttachments()->get();
```

Use a `filter()` to limit the results to one field or one type of file.

```php
$logo = $company->fileAttachments()->filter('@field', 'logo')->first();
$spreadsheets = $job->fileAttachments()->filter('@ext', 'xls')->get();
```

Finally, call `getContent()` on a FileAttachment model to read the content of the file. The library automatically decodes the content for you.

```php
$attachment->getContent();
```

## Reports

Fluently run reports using the report builder.

### Passing parameters

Pass parameters to the report using the ``parameter()`` or ``namedParameter()`` methods. The ``parameter()`` method accepts two arguments: the report parameter ID and the value.

```php
$pace->report(1000)
   ->parameter(10001, '2019-12-01')
   ->parameter(10002, '2019-12-31')
   ->parameter(10003, 'D');
```

The ``namedParameter()`` method looks up the report parameter ID by its name.

```php
$pace->report(1000)
   ->namedParameter('Start Date', '2019-12-01')
   ->namedParameter('End Date', '2019-12-31')
   ->namedParameter('Report Format', 'D');
```

### Reports requiring a base object

Some reports require a base object. Use the ``baseObjectKey()`` method to pass a model or primary key.

```php
$job = $pace->model('Job')->read('90000');

$pace->report(100)
    ->baseObjectKey($job)
    ->namedParameter('Include Kit Detail', 'N');
```

### Getting the report

The report builder ``get()`` method returns a ``Report\File`` instance, which has two public methods: ``getContent()`` returns the report file content and ``getMediaType()`` returns the media (MIME) type of the file. 

```php
$file = $pace->report(200)->get();

if ($file->getMediaType() == 'application/vnd.ms-excel') {
    file_put_contents('report.xls', $file->getContent());
}
```

### Printing the report

Use the ``print()`` method to print the report to the default printer. If the report does not have a default printer configured, the Pace API will throw a SOAP error.

```php
$pace->report(100)
    ->baseObjectKey('90000')
    ->namedParameter('Include Kit Detail', 'N')
    ->print();
```

## Invoke Action

The invoke action service methods are exposed as PHP methods. You can find a list of methods and their arguments in the InvokeAction.wsdl file provided with the Pace SDK. Arguments must be passed in the order specified in the WSDL.

```php
$estimate = $pace->model('Estimate')->read(100000);
$pace->invokeAction()->calculateEstimate($estimate);
```

You can also use named arguments to pass the arguments out of order, or you can mix ordered and named arguments.

```php
$poLine = $pace->model('PurchaseOrder')->read(50000)->purchaseOrderLines()->first();
$pace->invokeAction()->receivePurchaseOrderLine($poLine, Carbon::now(), in3: 'Receiving note.', in5: 1);
```

If the method requires a complex type, you will need to pass an array.

If one of the arguments is an instance of a model, it will automatically be converted to a complex type containing the model's primary key. The two examples above make use of this feature. Additionally, if your complex type array contains a model, it will automatically be converted to the model's primary key.

```php
$productType = $pace->model('JobProductType')->read('FL');
$result = $pace->invokeAction()->createEstimate([
    'customer' => 'HOUSE',
    'estimateDescription' => 'Testing',
    'estimatePartInfo' => [
        'product' => $productType,
        'quantity1' => 100,
        'finalSizeW' => 8.5,
        'finalSizeH' => 11,
        'colorsSide1' => 4,
        'colorsSide2' => 0,
        'totalColors' => 4,
        'eachOf' => 1,
        'grainSpecifications' => 1,
    ],
]);
```

Finally, the result of the invoke action call can be accessed like an array, converted to an array, or converted to a model (if the method returns the matching complex type).

```php
$result['estimateNumber']; // returns the estimate number
$result->toArray(); // returns an array
$result->toModel('Estimate'); // returns an estimate model
```

## Version

Identify the version of Pace running on the server.

```php
$pace->version();
```

The above will return:

```
array(4) {
  ["string"]=>
  string(24) "27.12-750 (201512111349)"
  ["major"]=>
  int(27)
  ["minor"]=>
  int(12)
  ["patch"]=>
  int(750)
}
```