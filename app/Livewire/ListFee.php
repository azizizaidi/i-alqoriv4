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
use Illuminate\Support\Facades\Log;

class ListFee extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public $finalhour;

    /**
     * ✅ Enhanced sync all transactions from ToyyibPay
     */
    public function syncToyyibpayData()
    {
        Log::info('User initiated full ToyyibPay sync', ['user_id' => auth()->id()]);
        
        $paymentController = new PaymentController();
        
        // Show loading notification
        Notification::make()
            ->title('Memulakan Penyelarasan...')
            ->info()
            ->body('Sedang menyelaraskan data dengan ToyyibPay. Sila tunggu sebentar.')
            ->send();
        
        // Use enhanced syncAllUnpaidBills method
        $result = $paymentController->syncAllUnpaidBills();

        $message = "Penyelarasan selesai. {$result['total_checked']} transaksi disemak, {$result['updated']} dikemaskini";
        
        if (isset($result['skipped']) && $result['skipped'] > 0) {
            $message .= ", {$result['skipped']} dilangkau";
        }
        
        if ($result['errors'] > 0) {
            $message .= ", {$result['errors']} ralat";
            
            // Log error details for admin review
            if (!empty($result['error_details'])) {
                Log::warning('Sync errors details:', [
                    'user_id' => auth()->id(),
                    'errors' => $result['error_details']
                ]);
            }
            
            // Show warning notification if there are errors
            Notification::make()
                ->title('Penyelarasan Selesai dengan Ralat')
                ->warning()
                ->body($message . '. Sila semak log untuk butiran ralat.')
                ->seconds(15)
                ->send();
                
            return;
        }

        Notification::make()
            ->title('Penyelarasan Data Berjaya')
            ->success()
            ->body($message)
            ->seconds(10)
            ->send();
            
        Log::info('ToyyibPay sync completed successfully', [
            'user_id' => auth()->id(),
            'result' => $result
        ]);
    }

    /**
     * ✅ Enhanced sync from system records
     */
    public function syncFromSystemRecords()
    {
        Log::info('User initiated system records sync', ['user_id' => auth()->id()]);
        
        $paymentController = new PaymentController();
        
        // Show loading notification
        Notification::make()
            ->title('Memulakan Penyelarasan Sistem...')
            ->info()
            ->body('Sedang menyelaraskan rekod sistem dengan ToyyibPay.')
            ->send();
            
        $result = $paymentController->syncUnpaidBillsFromSystem();

        $message = "Penyelarasan dari sistem selesai. {$result['total_checked']} rekod disemak, {$result['updated']} dikemaskini";
        
        if ($result['errors'] > 0) {
            $message .= ", {$result['errors']} ralat";
            
            Log::warning('System sync errors:', [
                'user_id' => auth()->id(),
                'errors' => $result['error_details'] ?? []
            ]);
            
            Notification::make()
                ->title('Penyelarasan Sistem Selesai dengan Ralat')
                ->warning()
                ->body($message . '. Sila semak log untuk butiran ralat.')
                ->seconds(15)
                ->send();
                
            return;
        }

        Notification::make()
            ->title('Penyelarasan Sistem Berjaya')
            ->success()
            ->body($message)
            ->seconds(10)
            ->send();
            
        Log::info('System sync completed successfully', [
            'user_id' => auth()->id(),
            'result' => $result
        ]);
    }

    /**
     * ✅ New method: Sync selected records only
     */
    public function syncSelectedRecords(array $recordIds)
    {
        if (empty($recordIds)) {
            Notification::make()
                ->title('Tiada Rekod Dipilih')
                ->warning()
                ->body('Sila pilih rekod untuk disync.')
                ->send();
            return;
        }

        Log::info('User initiated selected records sync', [
            'user_id' => auth()->id(),
            'record_ids' => $recordIds
        ]);

        $paymentController = new PaymentController();
        
        Notification::make()
            ->title('Memulakan Sync Terpilih...')
            ->info()
            ->body('Sedang menyelaraskan ' . count($recordIds) . ' rekod terpilih.')
            ->send();
            
        $result = $paymentController->syncSpecificReports($recordIds);

        $message = "{$result['total_checked']} rekod dipilih disemak, {$result['updated']} dikemaskini";
        
        if ($result['errors'] > 0) {
            $message .= ", {$result['errors']} ralat";
        }

        $notificationType = $result['errors'] > 0 ? 'warning' : 'success';
        $title = $result['errors'] > 0 ? 'Sync Terpilih Selesai dengan Ralat' : 'Sync Terpilih Berjaya';

        Notification::make()
            ->title($title)
            ->$notificationType()
            ->body($message)
            ->seconds(10)
            ->send();
            
        Log::info('Selected records sync completed', [
            'user_id' => auth()->id(),
            'result' => $result
        ]);
    }

    /**
     * ✅ Enhanced single payment check with better error handling
     */
    public function checkSinglePayment($recordId)
    {
        Log::info('User checking single payment', [
            'user_id' => auth()->id(),
            'record_id' => $recordId
        ]);
        
        $paymentController = new PaymentController();
        $result = $paymentController->getPaymentDetails($recordId);
        
        if ($result['found']) {
            $transaction = $result['transaction'];
            
            if (isset($transaction['billpaymentStatus']) && $transaction['billpaymentStatus'] == '1') {
                // Update record if paid
                $record = ReportClass::find($recordId);
                if ($record && $record->status != 1) {
                    try {
                        $record->status = 1;
                        
                        // Use the enhanced date parsing from PaymentController
                        if (isset($transaction['billpaymentDate']) && !empty($transaction['billpaymentDate'])) {
                            $record->transaction_time = \Carbon\Carbon::parse($transaction['billpaymentDate']);
                        } else {
                            $record->transaction_time = now();
                        }
                        
                        $record->save();
                        
                        Notification::make()
                            ->title('Pembayaran Ditemui!')
                            ->success()
                            ->body('Status pembayaran telah dikemaskini dengan waktu transaksi: ' . $record->transaction_time->format('d/m/Y H:i:s'))
                            ->seconds(10)
                            ->send();
                            
                        Log::info('Single payment updated successfully', [
                            'record_id' => $recordId,
                            'transaction_time' => $record->transaction_time
                        ]);
                        
                    } catch (\Exception $e) {
                        Log::error('Failed to update single payment', [
                            'record_id' => $recordId,
                            'error' => $e->getMessage()
                        ]);
                        
                        Notification::make()
                            ->title('Ralat Kemaskini')
                            ->danger()
                            ->body('Gagal mengemaskini status pembayaran: ' . $e->getMessage())
                            ->send();
                    }
                } else {
                    Notification::make()
                        ->title('Sudah Dibayar')
                        ->info()
                        ->body('Rekod ini sudah menunjukkan status dibayar.')
                        ->send();
                }
            } else {
                $status = $transaction['billpaymentStatus'] ?? 'N/A';
                Notification::make()
                    ->title('Belum Dibayar')
                    ->warning()
                    ->body("Pembayaran belum selesai di ToyyibPay. Status: {$status}")
                    ->send();
            }
        } else {
            $errorMessage = 'Tiada transaksi ditemui untuk rekod ini.';
            if (isset($result['error'])) {
                $errorMessage .= ' Error: ' . $result['error'];
                Log::warning('Single payment check failed', [
                    'record_id' => $recordId,
                    'error' => $result['error']
                ]);
            }
            
            Notification::make()
                ->title('Tiada Rekod')
                ->info()
                ->body($errorMessage)
                ->send();
        }
    }

    /**
     * ✅ Bulk sync selected records
     */
    public function bulkSyncSelected(Collection $records)
    {
        $recordIds = $records->pluck('id')->toArray();
        $this->syncSelectedRecords($recordIds);
    }

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                Action::make('sync')
                    ->label('Sync Semua Data ToyyibPay')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Sync Semua Data ToyyibPay')
                    ->modalDescription('Ini akan menyelaraskan semua transaksi dari ToyyibPay. Proses ini mungkin mengambil masa beberapa minit.')
                    ->modalSubmitActionLabel('Ya, Sync Sekarang')
                    ->action('syncToyyibpayData'),
                    
                Action::make('sync_system')
                    ->label('Sync Dari Sistem')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('secondary')
                    ->requiresConfirmation()
                    ->modalHeading('Sync Dari Rekod Sistem')
                    ->modalDescription('Ini akan menyelaraskan rekod belum bayar dalam sistem dengan ToyyibPay.')
                    ->modalSubmitActionLabel('Ya, Sync Sekarang')
                    ->action('syncFromSystemRecords'),
                    
                Action::make('refresh')
                    ->label('Refresh Jadual')
                    ->icon('heroicon-c-arrow-path-rounded-square')
                    ->color('gray')
                    ->action(fn () => $this->resetTable()),
            ])
            ->striped()
            ->query(function (){
                return ReportClass::with(['registrar', 'created_by'])
                    ->whereNotIn('month', ['null', '02-2022','03-2022', '04-2022'])
                    ->orderBy('created_at', 'desc');
            })
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('created_by.name')
                    ->label('Nama Guru')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(isIndividual: true)
                    ->sortable(),

                TextColumn::make('registrar.name')
                    ->label('Nama Klien')
                    ->toggleable()
                    ->searchable(isIndividual: true)
                    ->sortable(),

                TextColumn::make('registrar.code')
                    ->label('Kod Klien')
                    ->searchable()
                    ->toggleable()
                    ->sortable(),
                    
                TextColumn::make('registrar.phone')
                    ->label('No. Telefon')
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                TextColumn::make('month')
                    ->label('Bulan')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('fee_student')
                    ->label('Yuran')
                    ->currency('MYR')
                    ->toggleable()
                    ->sortable(),
                   
                TextColumn::make('note')
                    ->label('Nota')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(50),

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
                    })
                    ->sortable(),

                ImageColumn::make('receipt')
                    ->label('Resit')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->disk('public')
                    ->circular()
                    ->visibility('public'),
                    
                TextColumn::make('transaction_time')
                    ->label('Waktu Transaksi')
                    ->toggleable()
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->placeholder('Tiada'),
                    
                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        0 => 'Belum Bayar',
                        1 => 'Dah Bayar',
                        2 => 'Dalam Proses Transaksi',
                        3 => 'Gagal Bayar',
                        4 => 'Dalam Proses',
                        5 => 'Yuran Terlebih',
                    ])
                    ->default(null),

                SelectFilter::make('month')
                    ->label('Bulan')
                    ->options([
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
                        '07-2025' => 'Julai 2025',
                        '08-2025' => 'Ogos 2025',
                        '09-2025' => 'September 2025',
                        '10-2025' => 'Oktober 2025',
                        '11-2025' => 'November 2025',
                        '12-2025' => 'Disember 2025',
                    ])
                    ->searchable(),
                
                SelectFilter::make('created_by')
                    ->label('Guru')
                    ->relationship('created_by', 'name')
                    ->searchable()
                    ->preload(),
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
                
                        $filename = 'invois_' . $record->id . '_' . now()->format('YmdHis') . '.pdf';
                
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
                            ])
                            ->required(),
                        TextInput::make('note')
                            ->label('Nota')
                            ->maxLength(255)
                    ])
                    ->action(function (array $data, ReportClass $record): void {
                        $oldStatus = $record->status;
                        $record->status = $data['status'];
                        $record->note = $data['note'];
                        
                        // If manually setting to paid, set transaction time
                        if ($data['status'] == 1 && $oldStatus != 1 && !$record->transaction_time) {
                            $record->transaction_time = now();
                        }
                        
                        $record->save();
                        
                        Log::info('Manual status update', [
                            'user_id' => auth()->id(),
                            'record_id' => $record->id,
                            'old_status' => $oldStatus,
                            'new_status' => $data['status'],
                            'note' => $data['note']
                        ]);
                        
                        Notification::make()
                            ->title('Status Dikemaskini')
                            ->success()
                            ->body('Status rekod telah berjaya dikemaskini.')
                            ->send();
                    }),

                Action::make('check_payment')
                    ->label('Semak Bayaran')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('info')
                    ->visible(fn(ReportClass $record): bool => $record->status == 0)
                    ->requiresConfirmation()
                    ->modalHeading('Semak Status Pembayaran')
                    ->modalDescription('Ini akan menyemak status pembayaran terkini dari ToyyibPay.')
                    ->modalSubmitActionLabel('Ya, Semak Sekarang')
                    ->action(function (ReportClass $record) {
                        $this->checkSinglePayment($record->id);
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->label('Eksport Excel'),
                        
                    BulkAction::make('sync_selected')
                        ->label('Sync Terpilih')
                        ->icon('heroicon-o-arrow-path')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('Sync Rekod Terpilih')
                        ->modalDescription('Ini akan menyelaraskan rekod yang dipilih dengan ToyyibPay.')
                        ->action(fn (Collection $records) => $this->bulkSyncSelected($records)),
                        
                    BulkAction::make('mark_paid')
                        ->label('Tandai Sebagai Dibayar')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Tandai Sebagai Dibayar')
                        ->modalDescription('Ini akan menandai semua rekod terpilih sebagai dibayar. Gunakan dengan berhati-hati.')
                        ->action(function (Collection $records) {
                            $updated = 0;
                            foreach ($records as $record) {
                                if ($record->status != 1) {
                                    $record->status = 1;
                                    $record->transaction_time = now();
                                    $record->save();
                                    $updated++;
                                }
                            }
                            
                            Log::info('Bulk mark as paid', [
                                'user_id' => auth()->id(),
                                'updated_count' => $updated,
                                'record_ids' => $records->pluck('id')->toArray()
                            ]);
                            
                            Notification::make()
                                ->title('Status Dikemaskini')
                                ->success()
                                ->body("{$updated} rekod telah ditandai sebagai dibayar.")
                                ->send();
                        }),
                    
                    BulkAction::make('delete')
                        ->requiresConfirmation()
                        ->label('Padam')
                        ->color('danger')
                        ->modalHeading('Padam Rekod')
                        ->modalDescription('Adakah anda pasti ingin memadam rekod yang dipilih? Tindakan ini tidak boleh dibatalkan.')
                        ->action(function (Collection $records) {
                            $count = $records->count();
                            $recordIds = $records->pluck('id')->toArray();
                            
                            $records->each->delete();
                            
                            Log::warning('Bulk delete records', [
                                'user_id' => auth()->id(),
                                'deleted_count' => $count,
                                'record_ids' => $recordIds
                            ]);
                            
                            Notification::make()
                                ->title('Rekod Dipadam')
                                ->success()
                                ->body("{$count} rekod telah berjaya dipadam.")
                                ->send();
                        })
                        ->icon('heroicon-s-trash'),
                ])
            ])
            ->emptyStateHeading('Tiada Rekod Yuran')
            ->emptyStateDescription('Tiada rekod yuran ditemui untuk paparan.')
            ->emptyStateIcon('heroicon-o-currency-dollar');
    }

    public function render(): View
    {
        return view('livewire.list-fee');
    }
}