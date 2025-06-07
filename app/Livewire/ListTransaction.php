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




class ListTransaction extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;
    use HasMonthOptions;
    use LivewireAlert;

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
            self::STATUS_PROCESSING => 'primary',
            self::STATUS_FAILED => 'info',
            self::STATUS_IN_PROCESS => 'gray',
            self::STATUS_OVERPAID => 'warning',
        ];
    }

    // Method generateMonthOptions() sekarang dari HasMonthOptions trait

    /**
     * Get excluded months for query
     *
     * @return array
     */
    protected function getExcludedMonths(): array
    {
        return ['null', 'mar2022', 'apr2022'];
    }

    public $alertMessage;
    public $alertType;

    public function table(Table $table): Table

   {

    $registrar_id =auth()->id();

        return $table

            ->striped()
            ->groups([


            ])
            ->query(function () use ($registrar_id) {
                return ReportClass::with(['registrar', 'created_by'])
                    ->where('registrar_id', $registrar_id)
                    ->whereNotIn('month', $this->getExcludedMonths());
            })
            ->paginated([5,10, 25, 50, 100])
            ->columns([

                    TextColumn::make('id'),


                    TextColumn::make('created_by.name')
                    ->label('Nama Guru')
                   // ->toggleable(isToggledHiddenByDefault: true)
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
                    SelectColumn::make('status')
                    ->options($this->getStatusOptions())

                    // ->tooltip(fn (Model $record): string => " {$record->options}")

                   //  ->selectablePlaceholder(false)
                     ->disabled()
                     ->toggleable(),

                     ImageColumn::make('receipt')
                     ->label('Resit')
                     ->toggleable(isToggledHiddenByDefault: true)
                     ->disk('public')
                     ->circular()
                    // ->defaultImageUrl(url('images/placeholder.png'))
                     ->visibility('public'),








            ])
            ->filters([
                SelectFilter::make('status')
                ->options($this->getStatusOptions()),

                SelectFilter::make('month')
                ->label('Bulan')
                ->searchable()
                ->preload()
                ->options($this->generateMonthOptions()),


            ])
            ->actions([
             //   Action::make('invois')
             //          ->icon('heroicon-s-eye')
             //          ->color('success')
             //          ->url(fn (ReportClass $record): string => route('filament.admin.pages.invoices', ['id' => $record])),


               Action::make('bayar')
                       ->icon('heroicon-m-credit-card')
                       ->color('success')
                       ->url(fn (ReportClass $pay): string => route('toyyibpay.createBill', $pay))
                       ->visible(fn (Model $record) => $record->status != 1),
               Action::make('pdf')
                       ->label('Resit')
                       ->color('danger')
                       ->icon('heroicon-c-clipboard-document-list')
                       ->action(function (Model $record) {
                           return response()->streamDownload(function () use ($record) {
                               echo Pdf::loadHtml(
                                   Blade::render('pdf-receipt', ['record' => $record])
                               )->stream();
                           }, $record->number . '.pdf-resit');
                       }),





            ])
            ->groupedBulkActions([

                    ExportBulkAction::make()
                    ->label('Eksport'),
                    //Tables\Actions\DeleteBulkAction::make(),



            ]);
    }

    public function render(): View
    {
        //$reports = ReportClass::all();
      //  dd($reports);
      //$this->alert('success', 'Basic Alert');
        return view('livewire.list-transaction');
    }

    public function someAction()
{
    // Perform some action

    // Set success alert
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


}
