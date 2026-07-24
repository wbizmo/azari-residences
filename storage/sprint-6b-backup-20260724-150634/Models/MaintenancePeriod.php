<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenancePeriod extends Model {
    protected $perPage = 10;
    protected $fillable = ['property_id','starts_on','ends_on','title','notes','blocks_booking','created_by'];
    protected function casts(): array { return ['starts_on'=>'date','ends_on'=>'date','blocks_booking'=>'boolean']; }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
}
