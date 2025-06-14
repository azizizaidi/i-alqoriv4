<?php

namespace App\Models;

use \DateTimeInterface;
use App\Traits\Auditable;
use App\Traits\MultiTenantModelTrait;
use App\Traits\MultiTenantModelTraitRegistrar;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Auth;

class ReportClass extends Model
{
    use SoftDeletes;
    use MultiTenantModelTrait;
   // use MultiTenantModelTraitRegistrar;
    use Auditable;
    use HasFactory;
    //use \Znck\Eloquent\Traits\BelongsToThrough;

    public $table = 'report_classes';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'transaction_time',
    ];

    protected $fillable = [
        'bill_code',
        'transaction_time',
       'registrar_id',
        'class_names_id',
        'date',
        'total_hour',
        'class_names_id_2',
        'date_2',
        'total_hour_2',
        'month',
        'allowance',
        'record_student',
        'created_at',
        'updated_at',
        'deleted_at',
        'created_by_id',
        'fee_student',
        'status',
        'note',
        'receipt',
       'allowance_note',
    ];


    protected $casts = [
        'date' => 'array',
        'date_2' => 'array',
    ];

    public function class_name()
   {

   return $this->belongsTo(ClassName::class, 'class_names_id');
    }

    public function class_name_2()
    {

    return $this->belongsTo(ClassName::class, 'class_names_id_2');
     }

   public function registrar()
    {

      return $this->belongsTo(User::class, 'registrar_id');
    }



    public function created_by()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    



    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('d-m-Y H:i:s');
    }

// Accessor for 'date' field
public function getDateAttribute($value)
{
    // Check if the value is JSON
    $dates = json_decode($value, true);
    
    // If it's not JSON, return the value as-is (assume old format is plain text)
    if (json_last_error() !== JSON_ERROR_NONE) {
        return $value;
    }
    
    // Handle the new JSON format
    return collect($dates)->pluck('date')->implode(', ');
}

// Mutator for 'date' field
public function setDateAttribute($value)
{
    if (is_string($value)) {
        $dates = explode(', ', $value);
        $this->attributes['date'] = json_encode(array_map(function($date) {
            return ['date' => $date];
        }, $dates));
    } elseif (is_array($value)) {
        $this->attributes['date'] = json_encode($value);
    }
}

// Accessor for 'date_2' field
public function getDate2Attribute($value)
{
    // Check if the value is JSON
    $dates = json_decode($value, true);
    
    // If it's not JSON, return the value as-is (assume old format is plain text)
    if (json_last_error() !== JSON_ERROR_NONE) {
        return $value;
    }
    
    // Handle the new JSON format
    return collect($dates)->pluck('date_2')->implode(', ');
}

// Mutator for 'date_2' field
public function setDate2Attribute($value)
{
    if (is_string($value)) {
        $dates = explode(', ', $value);
        $this->attributes['date_2'] = json_encode(array_map(function($date) {
            return ['date_2' => $date];
        }, $dates));
    } elseif (is_array($value)) {
        $this->attributes['date_2'] = json_encode($value);
    }
}

}
