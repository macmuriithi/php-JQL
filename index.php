<?php
require_once('JQL.php');
// Test the implementation
$jsonData = '[
    {"id": 1, "name": "John", "age": 30, "salary": 50000, "department": "IT", "city": "New York"},
    {"id": 2, "name": "Jane", "age": 25, "salary": 55000, "department": "HR", "city": "Los Angeles"},
    {"id": 3, "name": "Bob", "age": 35, "salary": 60000, "department": "IT", "city": "Chicago"},
    {"id": 4, "name": "Alice", "age": 28, "salary": 52000, "department": "Finance", "city": "New York"},
    {"id": 5, "name": "Charlie", "age": 40, "salary": 70000, "department": "IT", "city": "Los Angeles"}
]';

$jql = new JQL($jsonData);

// Test fluent interface
echo "Fluent Query 1:\n";
print_r($jql->select(['name', 'age'])->where('age', '>', 30)->orderBy('age', 'DESC')->get());

echo "\nFluent Query 2:\n";
print_r($jql->select(['department', 'COUNT(name)', 'AVG(salary)'])
    ->groupBy('department')
    ->orderBy('AVG(salary)', 'DESC')
    ->get());

// Test raw SQL query
echo "\nRaw SQL Query:\n";
print_r($jql->query('SELECT department, SUM(salary) WHERE age > 25 GROUP BY department'));


$jql = new JQL($jsonData);

$queries = [
    'SELECT name, age',
    'SELECT name, age, salary WHERE age > 30',
    'SELECT name, age ORDER BY age DESC',
    'SELECT department, SUM(salary) WHERE age > 25 GROUP BY department',
    'SELECT department, COUNT(name), AVG(salary) GROUP BY department ORDER BY AVG(salary) DESC'
];

foreach ($queries as $query) {
    echo "Query: $query\n";
    print_r($jql->query($query));
    echo "\n";
}
