<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\ReportClass;
use Illuminate\Http\Request;
use Carbon\Carbon;

class OverduePayment extends Component
{
    public $selectedYear = '';

    /**
     * Convert month format to Bahasa Melayu display
     *
     * @param string $month Format: MM-YYYY
     * @return string
     */
    private function formatMonthToMalay($month)
    {
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

        [$monthNum, $year] = explode('-', $month);
        return $months[$monthNum] . ' ' . $year;
    }

    /**
     * Generate an array of months for a specific year range
     *
     * @param int $startYear
     * @param int $endYear
     * @return array
     */
    private function generateMonthsArray($startYear, $endYear)
    {
        $months = [];

        for ($year = $startYear; $year <= $endYear; $year++) {
            // For 2022: exclude months 1 and 2 (start from March)
            // For all other years: include months 1 and 2 (start from January)
            $startMonth = ($year == 2022) ? 3 : 1;
            $endMonth = 12;

            for ($month = $startMonth; $month <= $endMonth; $month++) {
                $months[] = sprintf('%02d-%d', $month, $year);
            }
        }

        return $months;
    }

    /**
     * Get available years for selection
     *
     * @return array
     */
    private function getAvailableYears()
    {
        return [
            '' => 'Pilih Tahun',
            '2022' => '2022',
            '2023' => '2023',
            '2024' => '2024',
            '2025' => '2025',
        ];
    }

    /**
     * Get months for the selected year
     *
     * @return array
     */
    private function getMonthsForSelectedYear()
    {
        if (empty($this->selectedYear)) {
            return [];
        }

        return $this->generateMonthsArray($this->selectedYear, $this->selectedYear);
    }

    public function render()
    {
        return view('livewire.overdue-payment')->with([
            'reportclasses' => ReportClass::get(),
            'availableYears' => $this->getAvailableYears(),
            'monthsForYear' => $this->getMonthsForSelectedYear(),
            'formatMonthToMalay' => function($month) {
                return $this->formatMonthToMalay($month);
            },
        ]);
    }
}
