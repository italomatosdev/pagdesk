<?php

namespace App\Console;

use App\Services\SchedulerLogger;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Registrar heartbeat do scheduler a cada minuto
        $schedule->call(function () {
            SchedulerLogger::heartbeat();
        })->everyMinute()->name('scheduler:heartbeat');

        // Marcar parcelas atrasadas diariamente às 00:00
        $schedule->command('parcelas:marcar-atrasadas')
            ->daily()
            ->at('00:00')
            ->before(function () {
                SchedulerLogger::start('parcelas:marcar-atrasadas');
            })
            ->onSuccess(function () {
                SchedulerLogger::success('parcelas:marcar-atrasadas', 'Executed successfully');
            })
            ->onFailure(function () {
                SchedulerLogger::failed('parcelas:marcar-atrasadas', 'Task failed');
            });

        // Limpar registros antigos do scheduler (semanalmente)
        $schedule->call(function () {
            $deleted = SchedulerLogger::cleanup(30);
            SchedulerLogger::success('scheduler:cleanup', "Deleted {$deleted} old records");
        })->weekly()->sundays()->at('04:00')->name('scheduler:cleanup');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
