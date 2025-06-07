<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\ReportClass;
use Carbon\Carbon;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
          // Get current month in MM-YYYY format
          $currentMonth = Carbon::now()->format('m-Y');

          // Get month name and year for display
          $monthNum = Carbon::now()->format('n');
          $yearShort = Carbon::now()->format('y');

          // Sum the allowance_amount for the specified month
          $allowance = ReportClass::where('month', $currentMonth)->sum('allowance');
          $allowanceFormatted = 'RM' . number_format($allowance, 2); // Format the allowance

          //$registrarId = Auth::id(); // Assuming you want to filter by the currently authenticated user

          // Calculate the date range for the last three months
          $threeMonthsAgo = Carbon::now()->subMonths(3)->startOfMonth();
          $now = Carbon::now()->endOfMonth();

          $activestats = ReportClass::
          whereBetween('created_at', [$threeMonthsAgo, $now])
          ->distinct('registrar_id')
          ->withoutTrashed()
          ->count('registrar_id');

        return [
            Stat::make("Elaun Bulan {$monthNum}/{$yearShort}", $allowanceFormatted)

                ->color('success')
                ->extraAttributes([
                   // 'wire:click' => '$emit("filterUpdate", "is_admin")',
                    //'class' => 'cursor-pointer border-lime-400 ',
                ]),


            Stat::make('Jumlah Klien Aktif', $activestats)
            ->extraAttributes([
                // 'wire:click' => '$emit("filterUpdate", "is_admin")',
                // 'class' => 'cursor-pointer border-rose-400',
             ]),


         //   Stat::make('Jumlah Kelas Aktif', '7')
           // ->extraAttributes([
                // 'wire:click' => '$emit("filterUpdate", "is_admin")',
               //  'class' => 'cursor-pointer border-teal-400',
         //    ]),


        ];
    }


}
