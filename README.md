# BatchStreamHandler 

A [Monolog](https://github.com/Seldaek/monolog) handler that takes a batch of records and pushes them to a stream **at once**.

(Use ^2.0 or Monolog ^2.0 and ^1.0 for Monolog ^1.x)

## Why

When logging calls to webservers I like all log records of one call grouped together. I changed the default `StreamHandler` to handle only batches of records (`handleBatch`) and write them to the stream at once - voilà the `BatchStreamHandler`.

## Installation

Install with

```
composer require spazzmarticus/monolog-batchstreamhandler
```

## Usage

```php
use SpazzMarticus\BatchStreamHandler\BatchStreamHandler;
use Monolog\Handler\BufferHandler;
use Monolog\Logger;

$batchStreamHandler = new BatchStreamHandler('supsi-looking.log');

//Optional - Envelop the records with head and foot lines
$batchStreamHandler->pushHeadLine('-------');
$batchStreamHandler->pushFootLine('=======');

$bufferHandler = new BufferHandler($batchStreamHandler);

$logger = new Logger('supsi');
$logger->pushHandler($bufferHandler);

//PewPew - Do your stuff here
```

## `StreamHandler` vs `BatchStreamHandler`

The default `StreamHandler` pushes each record to the stream immediatly. Even when put after a BufferHandler (which buffers records until `flush()` is called) each record is processed and written to the stream seperatly.

Let's assume there are 3 parallel calls to the webserver `A`, `B`, `C`

```
  ------------------- Time ------------------->
A --[Debug]------[Notice]--------[Error]---|
B -[Warning][Warning]---------------|
C -----[Notice][Error]------------------------|
```

The log looks something like:

```
Warning
Debug
Notice
Warning
Error
Notice
Error
```

And that makes it hard to look at what happend at a specific calls.

With the `BatchStreamHandler` the log looks something like:

```
Warning
Warning
Debug
Notice
Error
Notice
Error
``` 

Which is still not superb, so I added enveloping with head and foot lines. 

Now this looks like a log I can work with:

```
-------
Warning
Warning
=======
-------
Debug
Notice
Error
=======
-------
Notice
Error
=======
``` 




