<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class PropertyPublicationReview extends Model {
    protected $guarded = [];
    protected function casts(): array { return ['checklist' => 'array']; }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}