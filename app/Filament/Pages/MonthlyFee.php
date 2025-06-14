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
        $unpaidBills = ReportClass::where('status', 0)->whereNotNull('bill_code')->get();
        $paymentController = new PaymentController();

        foreach ($unpaidBills as $bill) {
            $paymentController->billTransaction($bill->bill_code);
        }

        Notification::make()
            ->title('Penyelarasan Data Selesai')
            ->success()
            ->body('Status pembayaran telah diselaraskan dengan Toyyibpay.')
            ->send();
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
