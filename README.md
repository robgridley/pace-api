# Pace API Client

An unofficial PHP client library for EFI Pace's SOAP API, created by a Pace administator and PHP developer. This library makes make some assumptions about convention to make your life just a little bit easier.

This version is still being actively developed, and thus should not be considered stable, although the author is using it for several production projects.

## Installation

To install via [Composer](http://getcomposer.org/) add the following to your project's composer.json:

```javascript
"repositories": [
	{
		"type": "vcs",
		"url": "https://github.com/robgridley/pace-api"
	}
],
"require": {
	"robgridley/pace-api": "dev-master"
}
```

## To-dos

* Write more tests
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

KeyCollection also has a number of useful methods such as `all()`, `paginate()` and `first()`. In fact, the single object find example from earlier is just a shortcut to the `KeyCollection::first()` method.

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

## JSON

Both the Model and KeyCollection classes implemement JsonSerializable and casting either class to a string will generate JSON.

```php
// print a JSON representation of the House account
echo $pace->customer->read('HOUSE');
```
