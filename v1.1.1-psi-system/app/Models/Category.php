<?php
namespace App\Models;

use App\Core\Model;
use App\Core\HasCustomFields;

class Category extends Model
{
    use HasCustomFields;

    protected static string $table = 'categories';
    protected static array $fillable = ['name', 'attributes'];

    protected static function customFieldDefinitions(): array
    {
        return [
            'department' => ['label' => 'Department', 'type' => 'text', 'filterable' => true],
        ];
    }
}
