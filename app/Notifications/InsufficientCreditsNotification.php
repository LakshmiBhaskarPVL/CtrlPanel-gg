<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class InsufficientCreditsNotification extends Notification
{
    use Queueable;

    protected $servers;
    protected $totalCost;

    public function __construct($servers, $totalCost)
    {
        $this->servers = $servers;
        $this->totalCost = $totalCost;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject(Lang::get('Server Renewal Required'))
            ->line(Lang::get('You have servers that require renewal but insufficient credits.'))
            ->line(Lang::get('Available credits: :credits', ['credits' => $notifiable->credits]))
            ->line(Lang::get('Total cost for all servers: :cost', ['cost' => $this->totalCost]))
            ->action(Lang::get('Select Servers to Renew'), route('servers.pending-renewals'))
            ->line(Lang::get('Please select which servers you would like to renew with your available credits.'));
    }


    

    public function toArray($notifiable)
    {
        return [
            'message' => Lang::get('You have servers pending renewal. Please select which servers to renew.'),
            'action' => route('servers.pending-renewals'),
            'servers' => $this->servers,
            'total_cost' => $this->totalCost,
            'available_credits' => $notifiable->credits
        ];
    }
}