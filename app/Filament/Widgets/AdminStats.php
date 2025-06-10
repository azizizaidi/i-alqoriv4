<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\ReportClass;

class  AdminStats extends BaseWidget
{
    protected function getStats(): array
    {

        $fee = ReportClass::where('month', '05-2025')->sum('fee_student');
        $feeFormatted = 'RM' . number_format($fee, 2); // Format the allowance

        $allowance = ReportClass::where('month', '05-2025')->sum('allowance');
        $allowanceFormatted = 'RM' . number_format($allowance, 2); // Format the allowance

        $sumfeeoverdue = ReportClass::
        where('month','05-2025')
        ->where('status','!=',1)->sum('fee_student');
        $overdueFormatted = 'RM' . number_format( $sumfeeoverdue, 2);

        return [
            Stat::make('Jumlah Yuran Bulan Mei 25', $feeFormatted )
               // ->description('32k increase')
               // ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->extraAttributes([ 
                   // 'wire:click' => '$emit("filterUpdate", "is_admin")',
                    //'class' => 'cursor-pointer border-lime-400 ',
                ]), 


            Stat::make('Jumlah Elaun Bulan Mei 25',   $allowanceFormatted)
            ->extraAttributes([ 
                // 'wire:click' => '$emit("filterUpdate", "is_admin")',
                // 'class' => 'cursor-pointer border-rose-400',
             ]), 

              
            Stat::make('Baki Yuran Belum Bayar Mei 25', $overdueFormatted)
            ->extraAttributes([ 
                // 'wire:click' => '$emit("filterUpdate", "is_admin")',
               //  'class' => 'cursor-pointer border-teal-400',
             ]), 

              
        ];
    }


}
