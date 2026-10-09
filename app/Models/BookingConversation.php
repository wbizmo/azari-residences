<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingConversation extends Model
{
    protected $guarded = [];
    protected $casts = ['last_message_at' => 'datetime', 'closed_at' => 'datetime'];

    public function messages(): HasMany
    {
        return $this->hasMany(BookingMessage::class, 'conversation_id');
    }
}
