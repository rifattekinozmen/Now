<?php

namespace App\Providers;

use App\Contracts\Operations\OperationalNotifier;
use App\Services\Integrations\TotalEnergies\TotalEnergiesFuelQuoteService;
use App\Services\Operations\CompositeOperationalNotifier;
use App\Services\Operations\FreightEscalationEvaluator;
use App\Services\Operations\FreightEscalationRule;
use App\Services\Operations\LogOperationalNotifier;
use App\Services\Operations\SlackOperationalNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OperationalNotifier::class, fn (): OperationalNotifier => new CompositeOperationalNotifier([
            new LogOperationalNotifier,
            new SlackOperationalNotifier,
        ]));

        $this->app->singleton(FreightEscalationEvaluator::class);
        $this->app->bind(FreightEscalationRule::class);

        $this->app->singleton(
            TotalEnergiesFuelQuoteService::class,
            fn (): TotalEnergiesFuelQuoteService => TotalEnergiesFuelQuoteService::fromConfig(),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
