<?php

namespace App\Providers;

use App\Services\Validation\AbnValidator;
use App\Services\Validation\ArithmeticValidator;
use App\Services\Validation\DateValidator;
use App\Services\Validation\RequiredFieldsValidator;
use App\Services\Validation\ValidationService;
use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register the full validator set so the ValidationService surfaces
        // required-fields, ABN, arithmetic, and date errors together.
        $this->app->bind(ValidationService::class, function (): ValidationService {
            return new ValidationService([
                new RequiredFieldsValidator,
                new AbnValidator,
                new ArithmeticValidator,
                new DateValidator(Carbon::now()),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
