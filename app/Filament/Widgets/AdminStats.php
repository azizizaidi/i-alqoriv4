<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\ReportClass;
use Carbon\Carbon;

class  AdminStats extends BaseWidget
{
    protected function getStats(): array
    {
        // Get previous month in MM-YYYY format (postpaid concept)
        $currentMonth = Carbon::now()->subMonth()->format('m-Y');

        // Get month name and year for display (previous month)
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Mac', 4 => 'April',
            5 => 'Mei', 6 => 'Jun', 7 => 'Julai', 8 => 'Ogos',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Disember'
        ];
        $monthNumber = Carbon::now()->subMonth()->format('n');
        $monthName = $monthNames[$monthNumber];
        $yearShort = Carbon::now()->subMonth()->format('y');

        $fee = ReportClass::where('month', $currentMonth)->sum('fee_student');
        $feeFormatted = 'RM' . number_format($fee, 2); // Format the allowance

        $allowance = ReportClass::where('month', $currentMonth)->sum('allowance');
        $allowanceFormatted = 'RM' . number_format($allowance, 2); // Format the allowance

        $sumfeeoverdue = ReportClass::
        where('month', $currentMonth)
        ->where('status','!=',1)->sum('fee_student');
        $overdueFormatted = 'RM' . number_format( $sumfeeoverdue, 2);

        return [
            Stat::make("Jumlah Yuran Bulan {$monthName} {$yearShort}", $feeFormatted )
               // ->description('32k increase')
               // ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->extraAttributes([
                   // 'wire:click' => '$emit("filterUpdate", "is_admin")',
                    //'class' => 'cursor-pointer border-lime-400 ',
                ]),


            Stat::make("Jumlah Elaun Bulan {$monthName} {$yearShort}",   $allowanceFormatted)
            ->extraAttributes([
                // 'wire:click' => '$emit("filterUpdate", "is_admin")',
                // 'class' => 'cursor-pointer border-rose-400',
             ]),


            Stat::make("Baki Yuran Belum Bayar {$monthName} {$yearShort}", $overdueFormatted)
            ->extraAttributes([
                // 'wire:click' => '$emit("filterUpdate", "is_admin")',
               //  'class' => 'cursor-pointer border-teal-400',
             ]),


        ];
    }


}
