<?php

namespace Encore\Admin\Console;

use Illuminate\Database\Eloquent\Model;

class ResourceGenerator
{
    /**
     * @var Model
     */
    protected $model;

    /**
     * @var array
     */
    protected $formats = [
        'form_field'  => "\$form->%s('%s', __('%s'))",
        'show_field'  => "\$show->field('%s', __('%s'))",
        'grid_column' => "\$grid->column('%s', __('%s'))",
    ];

    /**
     * Raw database column type to field type category mapping.
     *
     * Replaces the former doctrine/dbal type mapping, which Laravel 11+
     * no longer supports. Covers MySQL, SQL Server and common PostgreSQL
     * type names; unknown types fall back to "string".
     *
     * @var array
     */
    protected static $columnTypeMap = [
        // strings
        'varchar' => 'string', 'char' => 'string', 'nvarchar' => 'string', 'nchar' => 'string',
        'enum' => 'string', 'set' => 'string', 'uniqueidentifier' => 'string', 'uuid' => 'string',
        'geometry' => 'string', 'point' => 'string', 'linestring' => 'string', 'polygon' => 'string',
        'geometrycollection' => 'string', 'multipoint' => 'string', 'multilinestring' => 'string',
        'multipolygon' => 'string',
        // text
        'text' => 'text', 'tinytext' => 'text', 'mediumtext' => 'text', 'longtext' => 'text',
        'ntext' => 'text', 'citext' => 'text',
        // integers
        'int' => 'integer', 'integer' => 'integer', 'bigint' => 'bigint', 'bigserial' => 'bigint',
        'smallint' => 'smallint', 'tinyint' => 'integer', 'serial' => 'integer', 'year' => 'integer',
        // floats
        'decimal' => 'decimal', 'numeric' => 'decimal', 'money' => 'decimal', 'smallmoney' => 'decimal',
        'float' => 'float', 'double' => 'float', 'double precision' => 'float', 'real' => 'float',
        // date & time
        'date' => 'date', 'datetime' => 'datetime', 'datetime2' => 'datetime', 'smalldatetime' => 'datetime',
        'timestamp' => 'timestamp', 'timestamptz' => 'timestamp', 'timestamp without time zone' => 'timestamp',
        'timestamp with time zone' => 'timestamp',
        'time' => 'time', 'time without time zone' => 'time', 'time with time zone' => 'time',
        // json
        'json' => 'json', 'jsonb' => 'json',
        // binary
        'blob' => 'blob', 'tinyblob' => 'blob', 'mediumblob' => 'blob', 'longblob' => 'blob',
        'binary' => 'blob', 'varbinary' => 'blob', 'bytea' => 'blob',
        // boolean
        'bit' => 'boolean', 'bool' => 'boolean', 'boolean' => 'boolean',
    ];

    /**
     * @var array
     */
    protected $fieldTypeMapping = [
        'ip'       => 'ip',
        'email'    => 'email|mail',
        'password' => 'password|pwd',
        'url'      => 'url|link|src|href',
        'mobile'   => 'mobile|phone',
        'color'    => 'color|rgb',
        'image'    => 'image|img|avatar|pic|picture|cover',
        'file'     => 'file|attachment',
    ];

    /**
     * ResourceGenerator constructor.
     *
     * @param mixed $model
     */
    public function __construct($model)
    {
        $this->model = $this->getModel($model);
    }

    /**
     * @param mixed $model
     *
     * @return mixed
     */
    protected function getModel($model)
    {
        if ($model instanceof Model) {
            return $model;
        }

        if (!class_exists($model) || !is_string($model) || !is_subclass_of($model, Model::class)) {
            throw new \InvalidArgumentException("Invalid model [$model] !");
        }

        return new $model();
    }

    /**
     * @return string
     */
    public function generateForm()
    {
        $reservedColumns = $this->getReservedColumns();

        $output = '';

        foreach ($this->getTableColumns() as $column) {
            $name = $column['name'];
            if (in_array($name, $reservedColumns)) {
                continue;
            }
            $type = $this->getColumnType($column);
            $default = $column['default'];

            $defaultValue = '';

            // set column fieldType and defaultValue
            switch ($type) {
                case 'boolean':
                case 'bool':
                    $fieldType = 'switch';
                    break;
                case 'json':
                case 'array':
                case 'object':
                    $fieldType = 'text';
                    break;
                case 'string':
                    $fieldType = 'text';
                    foreach ($this->fieldTypeMapping as $type => $regex) {
                        if (preg_match("/^($regex)$/i", $name) !== 0) {
                            $fieldType = $type;
                            break;
                        }
                    }
                    $defaultValue = "'{$default}'";
                    break;
                case 'integer':
                case 'bigint':
                case 'smallint':
                case 'timestamp':
                    $fieldType = 'number';
                    break;
                case 'decimal':
                case 'float':
                case 'real':
                    $fieldType = 'decimal';
                    break;
                case 'datetime':
                    $fieldType = 'datetime';
                    $defaultValue = "date('Y-m-d H:i:s')";
                    break;
                case 'date':
                    $fieldType = 'date';
                    $defaultValue = "date('Y-m-d')";
                    break;
                case 'time':
                    $fieldType = 'time';
                    $defaultValue = "date('H:i:s')";
                    break;
                case 'text':
                case 'blob':
                    $fieldType = 'textarea';
                    break;
                default:
                    $fieldType = 'text';
                    $defaultValue = "'{$default}'";
            }

            $defaultValue = $defaultValue ?: $default;

            $label = $this->formatLabel($name);

            $output .= sprintf($this->formats['form_field'], $fieldType, $name, $label);

            if (trim($defaultValue, "'\"")) {
                $output .= "->default({$defaultValue})";
            }

            $output .= ";\r\n";
        }

        return $output;
    }

    public function generateShow()
    {
        $output = '';

        foreach ($this->getTableColumns() as $column) {
            $name = $column['name'];

            // set column label
            $label = $this->formatLabel($name);

            $output .= sprintf($this->formats['show_field'], $name, $label);

            $output .= ";\r\n";
        }

        return $output;
    }

    public function generateGrid()
    {
        $output = '';

        foreach ($this->getTableColumns() as $column) {
            $name = $column['name'];
            $label = $this->formatLabel($name);

            $output .= sprintf($this->formats['grid_column'], $name, $label);
            $output .= ";\r\n";
        }

        return $output;
    }

    protected function getReservedColumns()
    {
        return [
            $this->model->getKeyName(),
            $this->model->getCreatedAtColumn(),
            $this->model->getUpdatedAtColumn(),
            'deleted_at',
        ];
    }

    /**
     * Get columns of a giving model.
     *
     * @return array[]
     */
    protected function getTableColumns()
    {
        $table = $this->model->getConnection()->getTablePrefix().$this->model->getTable();

        return $this->model->getConnection()->getSchemaBuilder()->getColumns($table);
    }

    /**
     * Map a raw database column type to a field type category.
     *
     * @param array $column A column entry from Schema::getColumns()
     *
     * @return string
     */
    protected function getColumnType(array $column)
    {
        $typeName = strtolower($column['type_name'] ?? '');

        // MySQL TINYINT(1) is the conventional boolean column.
        if ($typeName === 'tinyint' && str_contains(strtolower($column['type'] ?? ''), 'tinyint(1)')) {
            return 'boolean';
        }

        return static::$columnTypeMap[$typeName] ?? 'string';
    }

    /**
     * Format label.
     *
     * @param string $value
     *
     * @return string
     */
    protected function formatLabel($value)
    {
        return ucfirst(str_replace(['-', '_'], ' ', $value));
    }
}
