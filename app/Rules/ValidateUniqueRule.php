<?php

namespace App\Rules;

use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Validation\Rule;
use Exception;

class ValidateUniqueRule implements Rule
{
    /**
     * The table to run the query against.
     *
     * @var string
     */

    protected $table;

    /**
     * The column to check on.
     *
     * @var string
     */
    protected $column = 'language';

    /**
     * The variable use to check unique when update
     *
     * @var string
     */
    protected $ignore;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($table, $column = null)
    {
        $tbl = explode('|', $table);

        $this->table = $tbl[0];
        $this->ignore = @$tbl[1] ?? '';
        $this->column = $column ?? $this->column;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        try {
            $attr = explode('.', $attribute);

            $field = $attr[0] ?? 'id';
            $lang = $attr[1] ?? '';

            $unique = DB::table($this->table)
                ->where($field, $value)
                ->where($this->column, $lang);

            if ($this->ignore) {
                $igfIgnore = explode(',', $this->ignore);
                $ignoreField = $igfIgnore[0];
                $ignoreValue = $igfIgnore[1];

                $unique->where($ignoreField, '!=', $ignoreValue);
            }

            $query = (clone $unique)->first();

            if (@$query && @$query->id) {
                return false;
            } else {
                return true;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'This field is already exist.';
    }
}
