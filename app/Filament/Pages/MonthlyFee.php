<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use App\Http\Controllers\PaymentController;
use App\Models\ReportClass;
use Filament\Notifications\Notification;

class MonthlyFee extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Sync Data')
                ->icon('heroicon-o-arrow-path')
                ->action('syncToyyibpayData'),
        ];
    }

    public function syncToyyibpayData()
    {
        $paymentController = new PaymentController();
        
        // Use one of the available sync methods from PaymentController
        // Option 1: Sync all transactions from ToyyibPay
        $result = $paymentController->syncAllUnpaidBills();
        
        // Option 2: Alternative - sync from system records (uncomment to use instead)
        // $result = $paymentController->syncUnpaidBillsFromSystem();

        // Show notification based on results
        if ($result['errors'] > 0) {
            Notification::make()
                ->title('Penyelarasan Data Selesai dengan Ralat')
                ->warning()
                ->body("Dikemaskini: {$result['updated']}, Ralat: {$result['errors']}")
                ->seconds(10)
                ->send();
        } else {
            Notification::make()
                ->title('Penyelarasan Data Selesai')
                ->success()
                ->body("Status pembayaran telah diselaraskan. {$result['updated']} rekod dikemaskini.")
                ->seconds(8)
                ->send();
        }
    }

    protected static string $view = 'filament.pages.monthly-fee';
    
    protected static ?string $title = 'Yuran Bulanan';
    
    public static function getNavigationLabel(): string 
    {
        return __('Yuran Bulanan');
    }

    public function getHeading(): string 
    {
        return __('Yuran Bulanan');
    }

    public static function canAccess(): bool 
    {
        return auth()->user()->can('view_monthly_fee');
    }
}