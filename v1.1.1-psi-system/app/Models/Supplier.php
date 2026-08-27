<?php
namespace App\Models;

use App\Core\Model;
use App\Core\HasCustomFields;

class Supplier extends Model
{
    use HasCustomFields;

    protected static string $table = 'suppliers';
    protected static array $fillable = ['name', 'phone', 'email', 'address', 'attributes'];

    protected static function customFieldDefinitions(): array
    {
        return [
            'tax_id'        => ['label' => 'Tax ID',        'type' => 'text',   'filterable' => true],
            'region'        => ['label' => 'Region',        'type' => 'select', 'filterable' => true, 'options' => ['NA', 'EU', 'APAC', 'LATAM']],
            'payment_terms' => ['label' => 'Payment Terms', 'type' => 'text',   'filterable' => true],
        ];
    }
}
