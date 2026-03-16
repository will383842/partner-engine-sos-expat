<?php

namespace App\Providers;

use App\Models\Agreement;
use App\Models\Subscriber;
use App\Observers\AgreementObserver;
use App\Observers\SubscriberObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Agreement::observe(AgreementObserver::class);
        Subscriber::observe(SubscriberObserver::class);
    }
}
