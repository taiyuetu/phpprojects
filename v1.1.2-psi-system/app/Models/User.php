<?php
namespace App\Models;

use App\Core\Model;
use App\Core\HasCustomFields;

class User extends Model
{
    use HasCustomFields;

    protected static string $table = 'users';
    protected static array $fillable = ['name', 'email', 'password', 'role', 'attributes'];

    /**
     * User custom fields. Add/edit entries here to get them in the form, list, filter, and CSV.
     * Supported types: text, textarea, select, date, upload.
     * Set 'required' => true to enforce validation on save.
     */
    protected static function customFieldDefinitions(): array
    {
        return [
            'department'  => ['label' => 'Department',  'type' => 'select', 'filterable' => true, 'options' => ['Sales', 'Warehouse', 'Finance', 'Management', 'IT']],
            'phone'       => ['label' => 'Phone',       'type' => 'text',   'filterable' => true],
            'hire_date'   => ['label' => 'Hire Date',   'type' => 'date',   'filterable' => true],
            'notes'       => ['label' => 'Notes',       'type' => 'textarea', 'filterable' => false],
        ];
    }
}
