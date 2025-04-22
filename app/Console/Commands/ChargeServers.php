<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Notifications\ServersSuspendedNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ChargeServers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'servers:charge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Charge all users with severs that are due to be charged';

    /**
     * A list of users that have to be notified
     * @var array
     */
    protected $usersToNotify = [];

    protected $usersWithInsufficientCredits = [];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Server::whereNull('suspended')->with('user', 'product')->chunk(10, function ($servers) {
            /** @var Server $server */
            foreach ($servers as $server) {
                if (!$server->needsRenewal()) {
                    continue;
                }

                /** @var Product $product */
                $product = $server->product;
                /** @var User $user */
                $user = $server->user;

                // Calculate total cost of all servers that need renewal for this user
                $userServers = $user->servers()->whereNull('suspended')->get();
                $serversNeedingRenewal = $userServers->filter(fn($s) => $s->needsRenewal());
                $totalRenewalCost = $serversNeedingRenewal->sum(fn($s) => $s->product->price);

                // If user doesn't have enough credits for all renewals
                if ($user->credits < $totalRenewalCost && $serversNeedingRenewal->count() > 1) {
                    if (!isset($this->usersWithInsufficientCredits[$user->id])) {
                        $this->usersWithInsufficientCredits[$user->id] = [
                            'user' => $user,
                            'servers' => collect(),
                            'total_cost' => $totalRenewalCost
                        ];
                    }
                    $this->usersWithInsufficientCredits[$user->id]['servers']->push([
                        'id' => $server->id,
                        'name' => $server->name,
                        'price' => $product->price,
                        'next_billing' => $server->getNextBillingDate()
                    ]);
                    continue;
                }

                // Normal renewal process for users with sufficient credits
                if ($server->canceled || ($user->credits < $product->price && $product->price != 0)) {
                    try {
                        $this->line("<fg=yellow>{$server->name}</> from user: <fg=blue>{$user->name}</> has been <fg=red>suspended!</>");
                        $server->suspend();

                        if (!in_array($user, $this->usersToNotify)) {
                            array_push($this->usersToNotify, $user);
                        }
                    } catch (\Exception $exception) {
                        $this->error($exception->getMessage());
                    }
                } else {
                    $this->line("<fg=blue>{$user->name}</> Current credits: <fg=green>{$user->credits}</> Credits to be removed: <fg=red>{$product->price}</>");
                    $user->decrement('credits', $product->price);
                    DB::table('servers')->where('id', $server->id)->update(['last_billed' => $server->getNextBillingDate()]);
                }
            }

            // Notify users who need to select servers for renewal
            foreach ($this->usersWithInsufficientCredits as $userData) {
                Cache::put(
                    "user.{$userData['user']->id}.pending_renewals",
                    [
                        'servers' => $userData['servers'],
                        'total_cost' => $userData['total_cost'],
                        'available_credits' => $userData['user']->credits
                    ],
                    now()->addDay()
                );

                $userData['user']->notifyInsufficientCredits(
                    $userData['servers'],
                    $userData['total_cost']
                );
            }

            return $this->notifyUsers();
        });
    }

    /**
     * @return bool
     */
    public function notifyUsers()
    {
        if (!empty($this->usersToNotify)) {
            /** @var User $user */
            foreach ($this->usersToNotify as $user) {
                $suspendServers = $user->servers()->whereNotNull('suspended')->get();

                $this->line("<fg=yellow>Notified user:</> <fg=blue>{$user->name}</>");
                $user->notify(new ServersSuspendedNotification($suspendServers));
            }
        }

        #reset array
        $this->usersToNotify = array();
        return true;
    }
}
