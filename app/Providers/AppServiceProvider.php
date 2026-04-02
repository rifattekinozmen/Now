<?php

namespace App\Providers;

use App\Contracts\CustomerEngagementNotifier;
use App\Contracts\Finance\ScannedPdfOcrAdapter;
use App\Contracts\Operations\OperationalNotifier;
use App\Livewire\DockerFriendlyCacheManager;
use App\Services\Finance\NullScannedPdfOcrAdapter;
use App\Services\Integrations\TotalEnergies\TotalEnergiesFuelQuoteService;
use App\Services\Notifications\CompositeCustomerEngagementNotifier;
use App\Services\Notifications\HttpCustomerEngagementNotifier;
use App\Services\Notifications\LogCustomerEngagementNotifier;
use App\Services\Notifications\NullCustomerEngagementNotifier;
use App\Services\Operations\CompositeOperationalNotifier;
use App\Services\Operations\FreightEscalationEvaluator;
use App\Services\Operations\FreightEscalationRule;
use App\Services\Operations\LogOperationalNotifier;
use App\Services\Operations\SlackOperationalNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
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
        $this->app->bind(ScannedPdfOcrAdapter::class, function (): ScannedPdfOcrAdapter {
            $adapterClass = config('logistics.bank_statement.scanned_pdf_ocr_adapter');
            if (is_string($adapterClass) && $adapterClass !== '' && class_exists($adapterClass)) {
                $instance = app($adapterClass);
                if ($instance instanceof ScannedPdfOcrAdapter) {
                    return $instance;
                }
            }

            return new NullScannedPdfOcrAdapter;
        });

        $this->app->bind(OperationalNotifier::class, fn (): OperationalNotifier => new CompositeOperationalNotifier([
            new LogOperationalNotifier,
            new SlackOperationalNotifier,
        ]));

        $this->app->bind(CustomerEngagementNotifier::class, function (): CustomerEngagementNotifier {
            $driver = config('customer_engagement.driver', 'auto');
            if (is_string($driver)) {
                $driver = strtolower($driver);
            } else {
                $driver = 'auto';
            }

            if ($driver === 'null') {
                return new NullCustomerEngagementNotifier;
            }
            if ($driver === 'log') {
                return new LogCustomerEngagementNotifier;
            }
            if ($driver === 'http') {
                return new HttpCustomerEngagementNotifier;
            }
            if ($driver === 'composite') {
                return new CompositeCustomerEngagementNotifier([
                    new LogCustomerEngagementNotifier,
                    new HttpCustomerEngagementNotifier,
                ]);
            }

            $httpEndpoint = config('customer_engagement.http.endpoint');
            $smsOn = (bool) config('customer_engagement.sms.enabled', false);
            $smsEndpoint = config('customer_engagement.sms.endpoint');
            $whatsappOn = (bool) config('customer_engagement.whatsapp.enabled', false);
            $whatsappEndpoint = config('customer_engagement.whatsapp.endpoint');

            $hasSmsHttp = $smsOn && is_string($smsEndpoint) && $smsEndpoint !== '';
            $hasWaHttp = $whatsappOn && is_string($whatsappEndpoint) && $whatsappEndpoint !== '';
            $hasGenericHttp = is_string($httpEndpoint) && $httpEndpoint !== '';

            if ($hasGenericHttp || $hasSmsHttp || $hasWaHttp) {
                return new HttpCustomerEngagementNotifier;
            }

            $logExplicit = (bool) config('customer_engagement.enabled', false);

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
        $this->registerBladeDirectives();
    }

    /**
     * Register custom Blade directives.
     *
     * @cache('key', 60) ... @endcache
     *
     * Caches the enclosed HTML output. Use for static/rarely-changing
     * Blade sections (KPI cards, page headers). Do NOT use inside
     * Livewire wire: directive scopes — it will break reactivity.
     */
    protected function registerBladeDirectives(): void
    {
        Blade::directive('cache', function (string $expression): string {
            // Split on the last comma so keys like `'x' . foo()->bar, 30` are not broken by
            // Livewire/Blade inlining the expression into a single explode() call (4-arg bug).
            $expression = trim($expression);
            $lastComma = strrpos($expression, ',');
            if ($lastComma === false) {
                $key = $expression;
                $ttl = '60';
            } else {
                $key = trim(substr($expression, 0, $lastComma));
                $ttl = trim(substr($expression, $lastComma + 1));
            }

            return "<?php
                \$__cacheKey = (string) ({$key});
                \$__cacheTtl = (int) ({$ttl});
                \$__cachedHtml = \\Illuminate\\Support\\Facades\\Cache::get(\$__cacheKey);
                if (\$__cachedHtml !== null) { echo \$__cachedHtml; } else { ob_start();
            ?>";
        });

        Blade::directive('endcache', function (): string {
            return '<?php
                $__cachedHtml = ob_get_clean();
                Cache::put($__cacheKey, $__cachedHtml, $__cacheTtl);
                echo $__cachedHtml;
                } // end @cache
            ?>';
        });
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
