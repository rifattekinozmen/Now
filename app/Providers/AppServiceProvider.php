<?php

namespace App\Providers;

use App\Contracts\CustomerEngagementNotifier;
use App\Contracts\Operations\OperationalNotifier;
use App\Livewire\DockerFriendlyCacheManager;
use App\Services\Integrations\TotalEnergies\TotalEnergiesFuelQuoteService;
use App\Services\Notifications\HttpCustomerEngagementNotifier;
use App\Services\Notifications\LogCustomerEngagementNotifier;
use App\Services\Notifications\NullCustomerEngagementNotifier;
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
use Livewire\Compiler\Compiler;

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

        $this->app->bind(CustomerEngagementNotifier::class, function (): CustomerEngagementNotifier {
            $httpEndpoint = config('customer_engagement.http.endpoint');
            if (is_string($httpEndpoint) && $httpEndpoint !== '') {
                return new HttpCustomerEngagementNotifier;
            }

            $logExplicit = (bool) config('customer_engagement.enabled', false);
            $smsOn = (bool) config('customer_engagement.sms.enabled', false);
            $whatsappOn = (bool) config('customer_engagement.whatsapp.enabled', false);

            if ($logExplicit || $smsOn || $whatsappOn) {
                return new LogCustomerEngagementNotifier;
            }

            return new NullCustomerEngagementNotifier;
        });

        $this->app->singleton(FreightEscalationEvaluator::class);
        $this->app->bind(FreightEscalationRule::class);

        $this->app->singleton(
            TotalEnergiesFuelQuoteService::class,
            fn (): TotalEnergiesFuelQuoteService => TotalEnergiesFuelQuoteService::fromConfig(),
        );

        $this->app->extend('livewire.compiler', function (Compiler $compiler): Compiler {
            return new Compiler(
                new DockerFriendlyCacheManager($compiler->cacheManager->cacheDirectory)
            );
        });
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
