<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\ReportClass;

class ClientStats extends BaseWidget
{
    protected function getStats(): array
    {

           // Sum the allowance_amount for the specified month
           $fee = ReportClass::where('registrar_id',auth()->id())->where('month', '05-2025')->sum('fee_student');
           $feeFormatted = 'RM' . number_format($fee, 2); // Format the allowance

           $sumfeeoverdue = ReportClass::where('registrar_id',auth()->id())
                                        ->whereNotIn('month',['null','02-2022','03-2022','04-2022'])
                                        ->where('status','!=',1)->sum('fee_student');
           $overdueFormatted = 'RM' . number_format( $sumfeeoverdue, 2);
 
           //$registrarId = Auth::id(); // Assuming you want to filter by the currently authenticated user
 
           // Calculate the date range for the last three months
         
 
        
        return [
            Stat::make('Jumlah Yuran Bulan Mei 25',  $feeFormatted)
               // ->description('32k increase')
               // ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->extraAttributes([ 
                   // 'wire:click' => '$emit("filterUpdate", "is_admin")',
                    //'class' => 'cursor-pointer border-lime-400 ',
                ]), 


        

              
            Stat::make('Baki Yuran Belum Bayar', $overdueFormatted)
            ->extraAttributes([ 
                // 'wire:click' => '$emit("filterUpdate", "is_admin")',
               //  'class' => 'cursor-pointer border-teal-400',
             ]), 

              
        ];
    }


}
