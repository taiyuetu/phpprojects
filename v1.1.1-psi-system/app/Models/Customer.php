<?php
namespace App\Models;

use App\Core\Model;
use App\Core\HasCustomFields;

class Customer extends Model
{
    use HasCustomFields;

    protected static string $table = 'customers';
    protected static array $fillable = ['name', 'phone', 'email', 'address', 'attributes'];

    protected static function customFieldDefinitions(): array
    {
        return [
            'customer_type' => ['label' => 'Customer Type', 'type' => 'select', 'filterable' => true, 'options' => ['Retail', 'Wholesale', 'VIP']],
            'region'        => ['label' => 'Region',        'type' => 'text',   'filterable' => true],
        ];
    }
}
