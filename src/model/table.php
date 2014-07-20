<?php

/*
  +------------------------------------------------------------------------+
  | Caribou                                             		           |
  +------------------------------------------------------------------------+
  | Copyright (c) 2014 natronite     					                   |
  +------------------------------------------------------------------------+
  | This source file is subject to the New BSD License that is bundled     |
  | with this package in the file LICENSE                                  |
  |                                                                        |
  | If you did not receive a copy of the license and are unable to         |
  | obtain it through the world-wide-web, please send an email             |
  | to natronite@gmail.com so I can send you a copy immediately.           |
  +------------------------------------------------------------------------+
  | Authors: Nate Maegli <natronite@gmail.com>                             |
  +------------------------------------------------------------------------+
*/

namespace Natronite\Caribou\Model;

class Table
{

    /** @var  string */
    private $name;

    /** @var array */
    private $columns = [];

    /** @var  string */
    private $sql;

    /** @var array */
    private $indexes = [];

    /** @var array */
    private $references = [];

    /** @var  string */
    private $engine;

    /** @var  string */
    private $collation;

    /** @var  string */
    private $charset;

    /** @var  string */
    private $autoIncrement;

    function __construct($name)
    {
        $this->name = $name;
    }

    public static function fromSQL($sql)
    {
        $table = null;

        preg_match('|^CREATE TABLE `(.*)`|', $sql, $matches);

        if (count($matches) > 1) {
            $name = $matches[1];
            $columns = [];

            $lines = explode(PHP_EOL, $sql);
            /** @var Column $previousColumn */
            $previousColumn = false;
            foreach ($lines as $line) {
                preg_match('|^`(.*)`|', trim($line), $matches);
                if (count($matches) > 1) {
                    $column = Column::fromSQL($line);
                    if ($previousColumn) {
                        $column->setAfter($previousColumn->getName());
                    } else {
                        $column->setFirst(true);
                    }
                    $columns[$matches[1]] = $column;
                    $previousColumn = $column;
                }
            }
            $table = new Table($name);
            $table->setColumns($columns);
            $table->setSql($sql);

            preg_match(
                '|ENGINE\s?=?\s?(?<engine>\w*) (?:DEFAULT)?\s?CHARSET\s?=?\s?(?<charset>\w*)|',
                end($lines),
                $matches
            );

            $table->setEngine($matches['engine']);
            $table->setCharset($matches['charset']);

        } else {
            throw new \Exception("Can't read mysql create syntax.\n" . $sql);
        }
        return $table;
    }

    /**
     * @param Table $old
     * @param Table $new
     * @return bool|string
     */
    public static function computeAlter(Table $old, Table $new)
    {
        $oldColumnNames = array_keys($old->getColumns());
        $newColumnNames = array_keys($new->getColumns());

        $alterLines = [];

        // Check for changed columns
        $possiblyChangedColumnNames = array_intersect($oldColumnNames, $newColumnNames);
        foreach ($possiblyChangedColumnNames as $possiblyChangedColumnName) {
            $oldColumn = $old->getColumn($possiblyChangedColumnName);
            $newColumn = $new->getColumn($possiblyChangedColumnName);

            if ($oldColumn && $newColumn) {
                $alter = Column::computeAlter($oldColumn, $newColumn);
                if ($alter) {
                    $alterLines[] = $alter;
                }
            }
        }

        // check if columns have been added
        $addedColumnNames = array_diff($newColumnNames, $oldColumnNames);
        foreach ($addedColumnNames as $added) {
            $column = $new->getColumn($added);
            $query = "ADD COLUMN " . $column;
            if ($column->isFirst()) {
                $query .= " FIRST";
            } elseif ($column->getAfter() !== false) {
                $query .= " AFTER `" . $column->getAfter() . "`";
            }
            $alterLines[] = $query;
        }

        // check if columns have been removed
        $removedColumnNames = array_diff($oldColumnNames, $newColumnNames);
        foreach ($removedColumnNames as $removedColumnName) {
            $alterLines[] = "DROP COLUMN `" . $removedColumnName . "`";
        }

        if (!empty($alterLines)) {
            return "ALTER TABLE `" . $new->getName() . "`\n\t" . implode(",\n\t", $alterLines);
        }

        return false;
    }

    /**
     * @return array
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * @param array $columns
     */
    public function setColumns($columns)
    {
        $this->columns = $columns;
    }

    /**
     * @param string $name The name of the column to get
     * @return Column|false The requested Column or false
     */
    public function getColumn($name)
    {
        if (array_key_exists($name, $this->columns)) {
            return $this->columns[$name];
        }
        return false;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getAutoIncrement()
    {
        return $this->autoIncrement;
    }

    /**
     * @param string $autoIncrement
     */
    public function setAutoIncrement($autoIncrement)
    {
        $this->autoIncrement = $autoIncrement;
    }

    /**
     * @return string
     */
    public function getCharset()
    {
        return $this->charset;
    }

    /**
     * @param string $charset
     */
    public function setCharset($charset)
    {
        $this->charset = $charset;
    }

    /**
     * @return string
     */
    public function getCollation()
    {
        return $this->collation;
    }

    /**
     * @param string $collation
     */
    public function setCollation($collation)
    {
        $this->collation = $collation;
    }

    /**
     * @return string
     */
    public function getEngine()
    {
        return $this->engine;
    }

    /**
     * @param string $engine
     */
    public function setEngine($engine)
    {
        $this->engine = $engine;
    }

    /**
     * @return array
     */
    public function getIndexes()
    {
        return $this->indexes;
    }

    /**
     * @param array $indexes
     */
    public function setIndexes($indexes)
    {
        $this->indexes = $indexes;
    }

    /**
     * @return array
     */
    public function getReferences()
    {
        return $this->references;
    }

    /**
     * @param array $references
     */
    public function setReferences($references)
    {
        $this->references = $references;
    }

    /**
     * @return string
     */
    public function getSql()
    {
        return $this->sql;
    }

    /**
     * @param string $sql
     */
    public function setSql($sql)
    {
        $this->sql = $sql;
    }

}