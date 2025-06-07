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
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\IconColumn;
use Awcodes\FilamentBadgeableColumn\Components\Badge;
use Awcodes\FilamentBadgeableColumn\Components\BadgeableColumn;
use Filament\Tables\Columns\ColorColumn;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\BadgeColumn;
use Illuminate\Database\Eloquent\Model;
use Closure;
use Filament\Notifications\Notification;
use Jantinnerezo\LivewireAlert\LivewireAlert;
use App\Traits\HasMonthOptions;

class ListMonthlyFee extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;
    use LivewireAlert;
    use HasMonthOptions;

    public $alertMessage;
    public $alertType;
    public $finalhour;

    // Constants for payment status
    const STATUS_UNPAID = '0';
    const STATUS_PAID = '1';
    const STATUS_PROCESSING = '2';
    const STATUS_FAILED = '3';
    const STATUS_IN_PROCESS = '4';
    const STATUS_OVERPAID = '5';

    /**
     * Get payment status options
     *
     * @return array
     */
    protected function getStatusOptions(): array
    {
        return [
            self::STATUS_UNPAID => 'Belum Bayar',
            self::STATUS_PAID => 'Dah Bayar',
            self::STATUS_PROCESSING => 'Dalam Proses Transaksi',
            self::STATUS_FAILED => 'Gagal Bayar',
            self::STATUS_IN_PROCESS => 'Dalam Proses',
            self::STATUS_OVERPAID => 'Yuran Terlebih',
        ];
    }

    /**
     * Get status color mapping
     *
     * @return array
     */
    protected function getStatusColors(): array
    {
        return [
            self::STATUS_UNPAID => 'danger',
            self::STATUS_PAID => 'success',
            self::STATUS_PROCESSING => 'warning',
            self::STATUS_FAILED => 'info',
            self::STATUS_IN_PROCESS => 'gray',
            self::STATUS_OVERPAID => 'primary',
        ];
    }

    // Method generateMonthOptions() sekarang dari HasMonthOptions trait

    /**
     * Get months to exclude from queries
     *
     * @return array
     */
    protected function getExcludedMonths(): array
    {
        // You can customize this method to dynamically determine which months to exclude
        // For now, we'll keep the same exclusions as before
        return ['null', '02-2022', '03-2022', '04-2022'];
    }

    public function table(Table $table): Table
    {
        $registrar_id = auth()->id();

        return $table
            ->striped()
            ->groups([])
            ->query(function () use ($registrar_id) {
                return ReportClass::with(['registrar', 'created_by'])
                    ->where('registrar_id', $registrar_id)
                    ->whereNotIn('month', $this->getExcludedMonths())
                    ->orderBy('created_at', 'desc');
            })
            ->paginated([5, 10, 25, 50, 100])
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('created_by.name')
                    ->label('Nama Guru')
                    ->searchable(isIndividual: true),
                TextColumn::make('registrar.name')
                    ->label('Nama')
                    ->toggleable()
                    ->searchable(isIndividual: true),
                TextColumn::make('registrar.code')
                    ->label('Kod Kelas')
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
                    ->formatStateUsing(fn($state) => $this->getStatusOptions()[$state] ?? 'Unknown')
                    ->color(fn(string $state): string => $this->getStatusColors()[$state] ?? 'gray'),
                ImageColumn::make('receipt')
                    ->label('Resit')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->disk('public')
                    ->circular()
                    ->visibility('public'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options($this->getStatusOptions()),
                SelectFilter::make('month')
                    ->label('Bulan')
                    ->options($this->generateMonthOptions()),
            ])
            ->actions([
                Action::make('bayar')
                    ->icon('heroicon-m-credit-card')
                    ->color('success')
                    ->url(fn(ReportClass $pay): string => route('toyyibpay.createBill', $pay))
                    ->visible(fn(Model $record) => $record->status != 1),
              Action::make('pdf')
                    ->label('Invois')
                    ->color('danger')
                    ->icon('heroicon-c-clipboard-document-list')
                    ->action(function (ReportClass $record) {
                        $this->finalhour = $record->total_hour + ($record->total_hour_2 ?? 0); // Calculate finalhour

                        $pdf = Pdf::loadHtml(
                            Blade::render('pdf', ['value' => $record, 'finalhour' => $this->finalhour])
                        );

                        // Define the filename as "invois" followed by the record number
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

            ])
            ->groupedBulkActions([
                ExportBulkAction::make()->label('Eksport'),
            ]);
    }

    public function render(): View
    {
        return view('livewire.list-fee');
    }

    public function someAction()
    {
        // Perform some action
        $this->alertType = 'success';
        $this->alertMessage = 'Action completed successfully!';
    }

    public function save(): void
    {
        // ...
        Notification::make()
            ->title('Berjaya Buat Pembayaran')
            ->icon('heroicon-o-document-text')
            ->iconColor('success')
            ->success()
            ->duration(5000)
            ->send();
    }
    //end
}
