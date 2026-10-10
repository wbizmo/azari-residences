<?php

namespace App\Notifications;

use App\Models\DiningRequest;
use Illuminate\Notifications\Notification;

final class DiningArrivalReminder extends Notification
{
    public function __construct(private readonly DiningRequest $dining) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toArray(object $notifiable): array
    {
        return [
            'title'=>'Your dining reservation is approaching',
            'message'=>'Your provider-confirmed dining reservation is approaching. Review its details and contact the restaurant if plans change.',
            'url'=>route('user.dining.index'),
            'dining_request_id'=>$this->dining->id,
        ];
    }
}
