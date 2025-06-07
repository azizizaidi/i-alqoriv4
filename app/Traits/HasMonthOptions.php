<?php

namespace App\Traits;

use Carbon\Carbon;

trait HasMonthOptions
{
    /**
     * Generate month options dari Mac 2022 hingga bulan semasa sahaja
     * Auto-update setiap bulan melalui schedule task
     *
     * @return array
     */
    protected function generateMonthOptions(): array
    {
        $options = [];
        $startDate = Carbon::create(2022, 3, 1); // Start from March 2022
        $endDate = Carbon::now(); // Only until current month
        
        // Bahasa Melayu month names
        $months = [
            '01' => 'Januari',
            '02' => 'Februari',
            '03' => 'Mac',
            '04' => 'April',
            '05' => 'Mei',
            '06' => 'Jun',
            '07' => 'Julai',
            '08' => 'Ogos',
            '09' => 'September',
            '10' => 'Oktober',
            '11' => 'November',
            '12' => 'Disember',
        ];

        $current = $startDate->copy();
        
        while ($current->lte($endDate)) {
            $monthNum = $current->format('m');
            $year = $current->format('Y');
            $key = sprintf('%s-%s', $monthNum, $year);
            $monthName = $months[$monthNum];
            $options[$key] = sprintf('%s %s', $monthName, $year);
            
            $current->addMonth();
        }

        return $options;
    }
}
