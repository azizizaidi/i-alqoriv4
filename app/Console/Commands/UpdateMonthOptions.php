<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class UpdateMonthOptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'month:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update month options untuk filter - auto tambah bulan baru setiap bulan';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Memulakan update month options...');

        // Generate month options yang sama seperti dalam ReportClassResource
        $options = $this->generateMonthOptions();

        // Cache the options untuk performance (optional)
        Cache::put('month_year_options', $options, now()->addMonth());

        $this->info('Month options telah dikemaskini!');
        $this->info('Jumlah bulan tersedia: ' . count($options));
        $this->info('Bulan terkini ditambah: ' . end($options));

        return Command::SUCCESS;
    }

    /**
     * Generate month options sampai bulan semasa sahaja
     * Schedule task akan auto tambah bulan baru setiap bulan
     */
    private function generateMonthOptions(): array
    {
        $options = [];
        $startDate = Carbon::create(2022, 3, 1); // Start from March 2022
        $endDate = Carbon::now(); // Only until the current month

        // Bahasa Melayu month names
        $malayMonths = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Mac',
            4 => 'April',
            5 => 'Mei',
            6 => 'Jun',
            7 => 'Julai',
            8 => 'Ogos',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Disember'
        ];

        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $monthNum = $current->format('m');
            $year = $current->format('Y');
            $key = $monthNum . '-' . $year;
            $monthName = $malayMonths[(int)$monthNum];
            $options[$key] = $monthName . ' ' . $year;

            $current->addMonth();
        }

        return $options;
    }
}
