<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
//UserRepository
use App\Interfaces\UserRepositoryInterface;
use App\Repositories\UserDBRepository;
//OrquestadorRepository
use App\Interfaces\OrquestadorRepositoryInterface;
use App\Repositories\OrquestadorDBRepository;
//PostConversionPeticiones
use App\Interfaces\PostconversionPeticionesInterface;
use App\Repositories\PostconversionPeticionesRepository;
//PreValidacionesPeticiones
use App\Interfaces\PrevalidacionPeticionesInterface;
use App\Repositories\PrevalidacionPeticionesRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserDBRepository::class);
        $this->app->bind(OrquestadorRepositoryInterface::class, OrquestadorDBRepository::class);
        $this->app->bind(PostconversionPeticionesInterface::class, PostconversionPeticionesRepository::class);
        $this->app->bind(PrevalidacionPeticionesInterface::class, PrevalidacionPeticionesRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
