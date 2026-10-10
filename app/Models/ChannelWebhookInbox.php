<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class ChannelWebhookInbox extends Model
{
    protected $table = 'channel_webhook_inbox';
    protected $guarded = [];
    protected $hidden = ['encrypted_payload'];
    protected function casts(): array { return ['event_occurred_at'=>'datetime', 'processed_at'=>'datetime']; }
}
