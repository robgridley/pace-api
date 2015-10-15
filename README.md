# Pace API Client

An unofficial PHP client library for EFI Pace's SOAP API. This version is still being actively developed and should not be considered stable.

The library makes some assumptions about convention in order to make your life easier.

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

## Configuration

You'll need to create a Pace system user with "Allow remote API usage" enabled.

```php
use Pace\Client as Pace;
use Pace\Soap\Factory as SoapFactory;

$pace = new Pace(new SoapFactory(), 'epace-staging.domain.com', 'apiuser', 'apipassword');
```

## CRUDD

### Creating an object

To create an object you need an instance of the model from the Pace client. You can do this using the model() method or a dynamic property.

```php
$csr = $pace->csr;

$csr->name = 'Jose Bautista';
$csr->email = 'joeybats@bluejays.com';
$csr->save();
```

### Reading an object

```php
$csr = $pace->csr->read(1);
echo $csr->email; // print "joeybats@bluejays.com"
```

### Updating an object

```php
$csr->note = "Blue Jays got me thinking '92, and I love it.";
$csr->note .= "\n";
$csr->note .= "https://youtu.be/j3LV9wFQyzU";
$csr->save();
```

### Duplicating an object

You can duplicate an existing model by modifying its properties (if needed) and calling the duplicate() method. The duplicate() method will return a new model instance and restore the existing model to its original state.

The duplicate() method accepts one optional argument: a new primary key value. If you do not supply a primary key, Pace will automatically pick the next available increment.

```php
$csr->name = 'Adam Lind';
$newCsr = $csr->duplicate();

echo $csr->name; // print "Jose Bautista"
echo $newCsr->name; // print "Adam Lind"
```

### Deleting an object

The delete() method accepts one optional argument: the name of the primary key for the model. It defaults to 'id', which is correct in most cases.

```php
$newCsr->delete(); // delete "Adam Lind"
```

## Finding objects

### Basics

You can search for objects in Pace using XPath expressions. The included XPath builder class takes care of converting PHP native types.

You may chain as many conditions as you need.

```php
$jobs = $pace->job->filter('adminStatus/@openJob', true)
	->filter('@jobType', 1)->find();
```

### Operators

All of the operators supported by Pace are supported by the client: '=', '!=', '<', '>', '<=', '>='. The starts-with() and contains() XPath functions are also supported.

```php
$millionPlus = $pace->salesPerson->filter('@annualQuota', '>=', 1000000)->find();

$coated = $pace->inventoryItem->contains('@description', 'C2S')->find();
```

### Nested filters

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

Use the sort() method to sort your results. You'll need to supply an XPath expression and an optional direction boolean. The default is false (ascending).

```php
$jobs = $pace->job
	->filter('adminStatus/@openJob', true)
	->sort('customer/@custName')
	->sort('@job', true)
	->find();
```

## Dates

Dates are automatically converted to and from [Carbon](http://carbon.nesbot.com/) instances.

## Key Collections

The raw result of a 'findObjects' SOAP call is an array of primary keys. The library wraps that array in a KeyCollection object to provide you with a bunch of conveniences, and prevent unnecessary calls to the 'readObject' service by only loading models as they're needed.

You can loop over a KeyCollection like an array. Each model will be loaded as you interate over it. If you break out of the loop, the remaining models will never have been loaded.

```php
$estimates = $pace->estimate->filter('@entryDate', '>=', Carbon::yesterday())->find();

foreach ($estimates as $estimate) {
	echo $estimate->enteredBy;
}
```

KeyCollection also has a number of useful methods such as all(), paginate() and first().

```php
// print 'bringerofrain@bluejays.com'
echo $pace->csr->filter('@name', 'Josh Donaldson')->find()->first()->email;
```

## Relationships

### Loading related models

You can load related models automatically via dynamic methods.

For a "belongs to" relationship, the model property name and foreign model type need to match. For example, the customer object has a property named 'csr' which contains the primary key for the 'csr' object type.

```php
$customer = $pace->customer->read('HOUSE');
echo $customer->csr()->name;
```

To load "has many" related models, call the plural of the foreign model's type. It is assumed that the model has a primary key of 'id' and the foreign model stores this key in a propery named after the parent model's type. For example, the 'job' object stores the 'customer' object's primary key in a property named 'customer'.

```php
$jobs = $customer->jobs();
```

If you find an object which flies in the face of convention, you can call the public belongsTo() and hasMany() methods directly.

### Associating related models

If the model you're associating has a primary key of 'id', you can assign the model as a property value. Otherwise, you'll need to explicitly assign the primary key value.

```php
$batch = $pace->inventoryBatch;
$batch->save(); // save the model to generate a primary key

$line = $pace->inventoryLine;
$line->inventoryBatch = $batch;
```

## JSON

Both the Model and KeyCollection classes implemement JsonSerializable and casting either class to a string will generate JSON.

```php
echo $customer; // prints a JSON representation of the customer model
```
