<?php

namespace App\Livewire;

use App\Models\ReportClass;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Livewire\Component;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Blade;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use App\Http\Controllers\PaymentController;

class ListFee extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function syncToyyibpayData()
    {
        $paymentController = new PaymentController();
        
        // ✅ Use existing syncAllUnpaidBills method
        $result = $paymentController->syncAllUnpaidBills();

        $message = "Penyelarasan selesai. {$result['total_checked']} transaksi disemak, {$result['updated']} dikemaskini";
        
        if ($result['errors'] > 0) {
            $message .= ", {$result['errors']} ralat";
            
            // Log error details for debugging
            if (!empty($result['error_details'])) {
                \Log::warning('Sync errors:', $result['error_details']);
            }
        }

        Notification::make()
            ->title('Penyelarasan Data Selesai')
            ->success()
            ->body($message)
            ->send();
    }

    /**
     * ✅ Alternative sync method - use existing syncUnpaidBillsFromSystem
     */
    public function syncFromSystemRecords()
    {
        $paymentController = new PaymentController();
        $result = $paymentController->syncUnpaidBillsFromSystem();

        $message = "Penyelarasan dari sistem selesai. {$result['total_checked']} rekod disemak, {$result['updated']} dikemaskini";
        
        if ($result['errors'] > 0) {
            $message .= ", {$result['errors']} ralat";
        }

        Notification::make()
            ->title('Penyelarasan Sistem Selesai')
            ->success()
            ->body($message)
            ->send();
    }

    /**
     * ✅ Check single payment status using existing getPaymentDetails method
     */
    public function checkSinglePayment($recordId)
    {
        $paymentController = new PaymentController();
        $result = $paymentController->getPaymentDetails($recordId);
        
        if ($result['found']) {
            $transaction = $result['transaction'];
            
            if (isset($transaction['billpaymentStatus']) && $transaction['billpaymentStatus'] == '1') {
                // Update record if paid
                $record = ReportClass::find($recordId);
                if ($record && $record->status != 1) {
                    $record->status = 1;
                    if (isset($transaction['billpaymentDate'])) {
                        $record->transaction_time = \Carbon\Carbon::parse($transaction['billpaymentDate']);
                    } else {
                        $record->transaction_time = now();
                    }
                    $record->save();
                    
                    Notification::make()
                        ->title('Pembayaran Ditemui!')
                        ->success()
                        ->body('Status pembayaran telah dikemaskini.')
                        ->send();
                } else {
                    Notification::make()
                        ->title('Sudah Dibayar')
                        ->info()
                        ->body('Rekod ini sudah menunjukkan status dibayar.')
                        ->send();
                }
            } else {
                Notification::make()
                    ->title('Belum Dibayar')
                    ->warning()
                    ->body('Pembayaran belum selesai di ToyyibPay.')
                    ->send();
            }
        } else {
            $errorMessage = 'Tiada transaksi ditemui untuk rekod ini.';
            if (isset($result['error'])) {
                $errorMessage .= ' Error: ' . $result['error'];
            }
            
            Notification::make()
                ->title('Tiada Rekod')
                ->info()
                ->body($errorMessage)
                ->send();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                Action::make('sync')
                    ->label('Sync Data ToyyibPay')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action('syncToyyibpayData'),
                    
                Action::make('sync_system')
                    ->label('Sync Dari Sistem')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('secondary')
                    ->action('syncFromSystemRecords'),
            ])
            ->striped()
            ->query(function (){
                return ReportClass::with(['registrar', 'created_by'])
                    ->whereNotIn('month', ['null', '02-2022','03-2022', '04-2022'])
                    ->orderBy('created_at', 'desc');
            })
            ->paginated([5,10, 25, 50, 100])
            ->columns([
                TextColumn::make('id')
                    ->label('ID'),

                TextColumn::make('created_by.name')
                    ->label('Nama Guru')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(isIndividual: true),

                TextColumn::make('registrar.name')
                    ->label('Nama Klien')
                    ->toggleable()
                    ->searchable(isIndividual: true),

                TextColumn::make('registrar.code')
                    ->label('Kod Klien')
                    ->searchable()
                    ->toggleable(),
                    
                TextColumn::make('registrar.phone')
                    ->label('No. Telefon')
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                TextColumn::make('month')
                    ->label('Bulan')
                    ->toggleable()
                    ->searchable(),

                TextColumn::make('fee_student')
                    ->label('Yuran')
                    ->currency('MYR')
                    ->toggleable(),
                   
                TextColumn::make('note')
                    ->label('Nota')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->badge()
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        0 => 'Belum Bayar',
                        1 => 'Dah Bayar',
                        2 => 'Dalam Proses Transaksi',
                        3 => 'Gagal Bayar',
                        4 => 'Dalam Proses',
                        5 => 'Yuran Terlebih',
                        default => 'Tidak Diketahui'
                    })
                    ->color(fn (string $state): string => match ($state) {
                        '0' => 'danger',
                        '1' => 'success',
                        '2' => 'primary',
                        '3' => 'warning',
                        '4' => 'gray',
                        '5' => 'info',
                        default => 'gray',
                    }),

                ImageColumn::make('receipt')
                    ->label('Resit')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->disk('public')
                    ->circular()
                    ->visibility('public'),
                    
                TextColumn::make('transaction_time')
                    ->label('Waktu Transaksi')
                    ->toggleable()
                    ->dateTime('d/m/Y H:i:s'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        0 => 'Belum Bayar',
                        1 => 'Dah Bayar',
                        2 => 'Dalam Proses Transaksi',
                        3 => 'Gagal Bayar',
                        4 => 'Dalam Proses',
                        5 => 'Yuran Terlebih',
                    ]),

                SelectFilter::make('month')
                    ->label('Bulan')
                    ->options([
                        '03-2022' => 'Mac 2022',
                        '04-2022' => 'April 2022',
                        '05-2022' => 'Mei 2022',
                        '06-2022' => 'Jun 2022',
                        '07-2022' => 'Julai 2022',
                        '08-2022' => 'Ogos 2022',
                        '09-2022' => 'September 2022',
                        '10-2022' => 'Oktober 2022',
                        '11-2022' => 'November 2022',
                        '12-2022' => 'Disember 2022',
                        '01-2023' => 'Januari 2023',
                        '02-2023' => 'Februari 2023',
                        '03-2023' => 'Mac 2023',
                        '04-2023' => 'April 2023',
                        '05-2023' => 'Mei 2023',
                        '06-2023' => 'Jun 2023',
                        '07-2023' => 'Julai 2023',
                        '08-2023' => 'Ogos 2023',
                        '09-2023' => 'September 2023',
                        '10-2023' => 'Oktober 2023',
                        '11-2023' => 'November 2023',
                        '12-2023' => 'Disember 2023',
                        '01-2024' => 'Januari 2024',
                        '02-2024' => 'Februari 2024',
                        '03-2024' => 'Mac 2024',
                        '04-2024' => 'April 2024',
                        '05-2024' => 'Mei 2024',
                        '06-2024' => 'Jun 2024',
                        '07-2024' => 'Julai 2024',
                        '08-2024' => 'Ogos 2024',
                        '09-2024' => 'September 2024',
                        '10-2024' => 'Oktober 2024',
                        '11-2024' => 'November 2024',
                        '12-2024' => 'Disember 2024',
                        '01-2025' => 'Januari 2025',
                        '02-2025' => 'Februari 2025',
                        '03-2025' => 'Mac 2025',
                        '04-2025' => 'April 2025',
                        '05-2025' => 'Mei 2025',
                        '06-2025' => 'Jun 2025',
                    ]),
            ])
            ->actions([
                Action::make('pdf')
                    ->label('Invois')
                    ->color('danger')
                    ->visible(fn(): bool => auth()->user()->can('view-any User'))
                    ->icon('heroicon-c-clipboard-document-list')
                    ->action(function (ReportClass $record) {
                        $this->finalhour = $record->total_hour + ($record->total_hour_2 ?? 0);
                
                        $pdf = Pdf::loadHtml(
                            Blade::render('pdf', ['value' => $record, 'finalhour' => $this->finalhour])
                        );
                
                        $filename = 'invois' . $record->id . '.pdf';
                
                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, $filename, [
                            'Content-Type' => 'application/pdf',
                            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                            'Cache-Control' => 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0',
                            'Pragma' => 'no-cache',
                            'Expires' => '0',
                        ]);
                    }),
            
                Action::make('bayar')
                    ->icon('heroicon-m-credit-card')
                    ->color('danger')
                    ->visible(fn(ReportClass $record): bool => auth()->user()->can('view-any User') && $record->status != 1)
                    ->url(fn (ReportClass $pay): string => route('toyyibpay.createBill',$pay)),
           
                Action::make('sunting')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn(): bool => auth()->user()->can('view-any User'))
                    ->fillForm(fn (ReportClass $record): array => [
                        'status' => $record->status,
                        'note' => $record->note,
                    ])
                    ->form([
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                0 => 'Belum Bayar',
                                1 => 'Dah Bayar',
                                2 => 'Dalam Proses Transaksi',
                                3 => 'Gagal Bayar',
                                4 => 'Dalam Proses',
                                5 => 'Yuran Terlebih',
                            ]),
                        TextInput::make('note')
                            ->label('Nota')
                    ])
                    ->action(function (array $data, ReportClass $record): void {
                        $record->status = $data['status'];
                        $record->note = $data['note'];
                        $record->save();
                    }),

                // ✅ Use existing getPaymentDetails method instead of non-existent billTransaction
                Action::make('check_payment')
                    ->label('Semak Bayaran')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('info')
                    ->visible(fn(ReportClass $record): bool => $record->status == 0)
                    ->action(function (ReportClass $record) {
                        $this->checkSinglePayment($record->id);
                    }),
            ])
            ->groupedBulkActions([
                ExportBulkAction::make()
                    ->label('Eksport'),
                    
                BulkAction::make('delete')
                    ->requiresConfirmation()
                    ->label('Padam')
                    ->action(fn (Collection $records) => $records->each->delete())
                    ->icon('heroicon-s-trash'),
            ]);
    }

    public function render(): View
    {
        return view('livewire.list-fee');
    }
}