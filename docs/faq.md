# FAQ
Here you will find answers to the most frequently asked Rubix ML questions.

### What environment (SAPI) should I run Rubix in?
All Rubix programs are designed to run from the PHP [command line interface](http://php.net/manual/en/features.commandline.php) (CLI). The reason almost always boils down to performance and memory consumption.

If you want to serve your trained estimators in production then you can use the [Rubix Server](https://github.com/RubixML/Server) library to run an optimized standalone model server.

To run your script using the PHP command line interface (CLI), open a terminal window and enter:
```sh
$ php example.php
```

> **Note:** The PHP interpreter must be in your default PATH for the above syntax to work.

### I'm getting out of memory errors
Try adjusting the `memory_limit` option in your php.ini file to something more reasonable. We recommend setting this to *-1* (no limit) or slightly below your device's memory supply for best results.

You can temporarily set the `memory_limit` in your script by using the `ini_set()` function.

**Example**

```php
ini_set('memory_limit', '-1');
```

> **Note:** Training can require a lot of memory. The amount necessary will depend on the amount of training data and the size of your model. If you have more data than you can hold in memory, some learners will allow you to train in batches. See the section on [Online](online.md) learners for more information.

### What is a Tuple?
A *tuple* is a way to denote an immutable sequential hetorogeneous list with a predefined length. An *n-tuple* is a tuple with the length of n. In some languages, tuples are a separate datatype and their properties such as immutability are enforced by the compiler/interpreter, however in PHP, tuples are denoted by sequential arrays which are mutable as a side effect.

**Example**

```php
$tuple = ['first', 'second', 0.001]; // a 3-tuple
```

### How do I import data into my project?
There are many ways to import data into your script depending on where the data are stored. For example, if your data exist on a filesystem in comma separated (CSV) text format, you can use a library such as [League CSV](https://csv.thephpleague.com/) to read the data into memory. PHP also provides [native functions](https://www.php.net/manual/en/function.str-getcsv.php) for CSV manipulations if that's more your style.

### What is the difference between categorical and continuous data types?
In general, there are 2 classes of data types that Rubix distinguishes by convention.

Categorical (or *discrete*) data are those that describe a *qualitative* property of a sample such as *gender* or *city* and can be 1 of k possible values. Categorical feature types are always represented as  string internal types.

Continuous data are *quantitative* properties of samples such as *age* or *speed* and can be any number within the set of infinite real numbers. Continuous features are represented as either floating point or integer types internally.

### Does Rubix support multiprocessing?
Yes, Rubix supports parallel processing (multiprocessing) by utilizing a pluggable parallel computing [Backend](backends/api.md) under the hood. Objects that implement the [Parallel](parallel.md) interface are able to take advantage of parallel computing backends.

### Does Rubix support multithreading?
Not currently, however we plan to add CPU and GPU multithreading in the future.

### Does Rubix support Deep Learning?
Yes. Deep Learning is a subset of machine learning that involves forming higher-order representations of the input features such as edges and textures in an image or word meanings in natural language processing. A number of learners in Rubix support Deep Learning including the [Multi Layer Perceptron](classifiers/multi-layer-perceptron.md) classifier and [MLP Regressor](regressors/mlp-regressor.md).

### Does Rubix support Reinforcement Learning?
Not currently. Rubix is for *supervised* and *unsupervised* learning only.