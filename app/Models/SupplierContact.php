<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SupplierContact extends Model { protected $fillable=['supplier_id','name','job_title','phone','email','notes','is_primary']; protected $casts=['is_primary'=>'boolean']; public function supplier():BelongsTo{return $this->belongsTo(Supplier::class);} }
