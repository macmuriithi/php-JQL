<?php
class JQL {
    private $data;
    private $selectedFields = [];
    private $whereConditions = [];
    private $groupByFields = [];
    private $orderByFields = [];
    private $limitCount = null;
    private $offsetCount = null;

    public function __construct($json) {
        $this->data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON data");
        }
    }

    // Fluent interface methods
    public function select($fields) {
        $this->selectedFields = is_array($fields) ? $fields : [$fields];
        return $this;
    }

    public function where($field, $operator, $value) {
        $this->whereConditions[] = [$field, $operator, $value];
        return $this;
    }

    public function orderBy($field, $direction = 'ASC') {
        $this->orderByFields[] = [$field, $direction];
        return $this;
    }

    public function groupBy($fields) {
        $this->groupByFields = is_array($fields) ? $fields : [$fields];
        return $this;
    }

    public function limit($count, $offset = 0) {
        $this->limitCount = $count;
        $this->offsetCount = $offset;
        return $this;
    }

    public function get() {
        $query = $this->buildQuery();
        $result = $this->query($query);
        $this->reset();
        return $result;
    }

    private function buildQuery() {
        $parts = [];
        
        // SELECT
        $parts[] = 'SELECT ' . ($this->selectedFields ? implode(', ', $this->selectedFields) : '*');
        
        // WHERE
        if (!empty($this->whereConditions)) {
            $whereClauses = [];
            foreach ($this->whereConditions as $condition) {
                $whereClauses[] = "{$condition[0]} {$condition[1]} " . $this->escapeValue($condition[2]);
            }
            $parts[] = 'WHERE ' . implode(' AND ', $whereClauses);
        }
        
        // GROUP BY
        if (!empty($this->groupByFields)) {
            $parts[] = 'GROUP BY ' . implode(', ', $this->groupByFields);
        }
        
        // ORDER BY
        if (!empty($this->orderByFields)) {
            $orderClauses = [];
            foreach ($this->orderByFields as $order) {
                $orderClauses[] = "{$order[0]} {$order[1]}";
            }
            $parts[] = 'ORDER BY ' . implode(', ', $orderClauses);
        }
        
        // LIMIT and OFFSET
        if ($this->limitCount !== null) {
            $parts[] = "LIMIT {$this->limitCount}";
            if ($this->offsetCount > 0) {
                $parts[] = "OFFSET {$this->offsetCount}";
            }
        }
        
        return implode(' ', $parts);
    }

    private function escapeValue($value) {
        if (is_string($value)) {
            return "'" . str_replace("'", "''", $value) . "'";
        }
        return $value;
    }

    // Original query method
    public function query($query) {
        $parts = $this->parseQuery($query);
        return $this->execute($parts);
    }

    private function parseQuery($query) {
        $parts = [
            'SELECT' => [],
            'WHERE' => '',
            'GROUP BY' => [],
            'ORDER BY' => [],
        ];

        $clauses = preg_split('/\b(SELECT|WHERE|GROUP BY|ORDER BY)\b/', $query, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        
        for ($i = 0; $i < count($clauses); $i += 2) {
            $clauseName = trim($clauses[$i]);
            $clauseContent = isset($clauses[$i+1]) ? trim($clauses[$i+1]) : '';
            
            switch ($clauseName) {
                case 'SELECT':
                    $parts['SELECT'] = array_map('trim', explode(',', $clauseContent));
                    break;
                case 'WHERE':
                    $parts['WHERE'] = $clauseContent;
                    break;
                case 'GROUP BY':
                    $parts['GROUP BY'] = array_map('trim', explode(',', $clauseContent));
                    break;
                case 'ORDER BY':
                    $parts['ORDER BY'] = array_map('trim', explode(',', $clauseContent));
                    break;
            }
        }

        return $parts;
    }

    private function execute($parts) {
        $result = $this->data;

        if (!empty($parts['WHERE'])) {
            $result = $this->applyWhere($result, $parts['WHERE']);
        }

        if (!empty($parts['GROUP BY'])) {
            $result = $this->applyGroupBy($result, $parts['GROUP BY'], $parts['SELECT']);
        } else {
            $result = $this->applySelect($result, $parts['SELECT']);
        }

        if (!empty($parts['ORDER BY'])) {
            $result = $this->applyOrderBy($result, $parts['ORDER BY']);
        }

        return $result;
    }

    private function applyWhere($data, $condition) {
        return array_filter($data, function($row) use ($condition) {
            $evalCondition = preg_replace_callback(
                '/\b(\w+)\b/',
                function($matches) use ($row) {
                    return isset($row[$matches[1]]) ? $row[$matches[1]] : $matches[1];
                },
                $condition
            );
            return eval("return $evalCondition;");
        });
    }

    private function applyGroupBy($data, $groupBy, $select) {
        $result = [];
        foreach ($data as $row) {
            $key = implode('-', array_map(function($col) use ($row) { return $row[$col]; }, $groupBy));
            if (!isset($result[$key])) {
                $result[$key] = array_fill_keys($select, []);
                foreach ($groupBy as $col) {
                    $result[$key][$col] = $row[$col];
                }
            }
            foreach ($select as $col) {
                if ($this->isAggregate($col)) {
                    $func = $this->getAggregateFunction($col);
                    $field = $this->getAggregateField($col);
                    $result[$key][$col][] = $row[$field];
                }
            }
        }
        return $this->finalizeGroupBy(array_values($result));
    }

    private function finalizeGroupBy($data) {
        return array_map(function($row) {
            foreach ($row as $col => $values) {
                if (is_array($values)) {
                    $func = $this->getAggregateFunction($col);
                    $row[$col] = $this->calculateAggregate($func, $values);
                }
            }
            return $row;
        }, $data);
    }

    private function applySelect($data, $select) {
        return array_map(function($row) use ($select) {
            $result = [];
            foreach ($select as $col) {
                if ($this->isAggregate($col)) {
                    $func = $this->getAggregateFunction($col);
                    $field = $this->getAggregateField($col);
                    $result[$col] = $this->calculateAggregate($func, [$row[$field]]);
                } else {
                    $result[$col] = $row[$col] ?? null;
                }
            }
            return $result;
        }, $data);
    }

    private function applyOrderBy($data, $orderBy) {
        usort($data, function($a, $b) use ($orderBy) {
            foreach ($orderBy as $order) {
                $parts = explode(' ', $order);
                $col = $parts[0];
                $direction = isset($parts[1]) ? strtoupper($parts[1]) : 'ASC';
                
                if ($a[$col] == $b[$col]) continue;
                
                if ($direction === 'DESC') {
                    return $b[$col] <=> $a[$col];
                } else {
                    return $a[$col] <=> $b[$col];
                }
            }
            return 0;
        });
        return $data;
    }

    private function isAggregate($col) {
        return preg_match('/^(COUNT|SUM|AVG|MIN|MAX)\(.*\)$/', $col);
    }

    private function getAggregateFunction($col) {
        preg_match('/^(COUNT|SUM|AVG|MIN|MAX)/', $col, $matches);
        return $matches[1];
    }

    private function getAggregateField($col) {
        preg_match('/\((.*)\)/', $col, $matches);
        return $matches[1];
    }

    private function calculateAggregate($func, $values) {
        switch ($func) {
            case 'COUNT': return count($values);
            case 'SUM': return array_sum($values);
            case 'AVG': return array_sum($values) / count($values);
            case 'MIN': return min($values);
            case 'MAX': return max($values);
            default: throw new Exception("Unknown aggregate function: $func");
        }
    }

    // Reset the fluent interface state
    private function reset() {
        $this->selectedFields = [];
        $this->whereConditions = [];
        $this->groupByFields = [];
        $this->orderByFields = [];
        $this->limitCount = null;
        $this->offsetCount = null;
    }
}
