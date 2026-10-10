<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class ChannelOutboxEvent extends Model
{
    protected $table = 'channel_outbox';
    protected $guarded = [];
    protected $hidden = ['encrypted_payload'];
    protected function casts(): array { return ['next_attempt_at'=>'datetime', 'published_at'=>'datetime']; }
}
