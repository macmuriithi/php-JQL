# PHP-JQL

`PHP-JQL` (JSON Query Language) is a PHP implementation that enables querying of JSON data using both raw SQL-Like query strings and a fluent interface. It is designed to facilitate sophisticated data manipulation and retrieval operations, allowing you to leverage the power of SQL-like queries or a more intuitive, method-chaining approach.

## Overview

The `JQL` class offers two primary modes of interaction:

- **Raw Query Mode**: Allows you to define and execute queries in a SQL-like format directly on your JSON data.
- **Fluent Interface Mode**: Provides a chainable, method-based interface to build queries dynamically and programmatically.

This flexibility makes `JQL` suitable for a variety of use cases, from simple data retrieval to more complex querying needs involving filtering, sorting, grouping, and aggregation.

## Features

- **Select Fields**: Specify which fields to include in the results.
- **Filter Data**: Apply conditions to filter records based on field values.
- **Sort Results**: Order results by one or more fields.
- **Group Data**: Group records by specified fields and perform aggregate calculations.
- **Limit and Offset**: Control the number of results returned and where to start the results from.
- **Raw Query Support**: Write raw queries for direct execution on the JSON data.

## Installation

To use the `JQL` class, include the PHP file containing the class definition in your project:

```php
include 'JQL.php';
```

## Usage

### Creating an Instance

To start querying JSON data, create a new instance of the `JQL` class with your JSON data:

```php
$jsonData = '[{"name": "John", "age": 30}, {"name": "Jane", "age": 25}]';
$jql = new JQL($jsonData);
```

### Query Methods

#### Fluent Interface

- **`select($fields)`**: Specify the fields to include in the result.
- **`where($field, $operator, $value)`**: Define conditions to filter the data.
- **`orderBy($field, $direction = 'ASC')`**: Order the results by a specified field and direction.
- **`groupBy($fields)`**: Group the results by one or more fields.
- **`limit($count, $offset = 0)`**: Limit the number of results and specify an offset.
- **`get()`**: Execute the query and retrieve the results.

#### Raw Query Interface

- **`query($query)`**: Execute a raw SQL-like query string on the JSON data.

## Example

Here is an example of using the `JQL` class in both fluent and raw query modes:

**Fluent Interface Example:**

```php
$jsonData = '[{"name": "John", "age": 30}, {"name": "Jane", "age": 25}]';
$jql = new JQL($jsonData);

$result = $jql
    ->select(['name', 'age'])
    ->where('age', '>', 20)
    ->orderBy('age', 'ASC')
    ->limit(2)
    ->get();

print_r($result);
```

**Raw Query Example:**

```php
$jsonData = '[{"name": "John", "age": 30}, {"name": "Jane", "age": 25}]';
$jql = new JQL($jsonData);

$query = "SELECT name, age WHERE age > 20 ORDER BY age ASC LIMIT 2";
$result = $jql->query($query);

print_r($result);
```

## License

This project is licensed under the MIT License
