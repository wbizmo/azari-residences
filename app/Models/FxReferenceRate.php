<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class FxReferenceRate extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['observed_at'=>'datetime', 'expires_at'=>'datetime']; }
}
