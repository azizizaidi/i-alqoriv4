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
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
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
use App\Traits\HasMonthOptions;


class ListFee extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;
    use HasMonthOptions;

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
        return ['null', '02-2022', '03-2022', '04-2022'];
    }



    public function table(Table $table): Table



    {

        return $table

            ->striped()
            ->groups([


            ])
            ->query(function (){
                return ReportClass::with(['registrar', 'created_by'])
                   // ->where('registrar_id', $registrar_id)
                    ->whereNotIn('month', $this->getExcludedMonths())
                    ->orderBy('created_at', 'desc');
            })
            ->paginated([5,10, 25, 50, 100])
            ->columns([

                    TextColumn::make('id'),


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
                   ->formatStateUsing(fn ($state) => $this->getStatusOptions()[$state] ?? 'Unknown')
  //       IconColumn::make('status')
  //       ->icon(fn (string $state): string => match ($state) {
  //          '0' => 'heroicon-s-table-cells',
  //         '1' => 'si-ticktick',
  //         '2' => 'fas-hand-holding-usd',
  //         '3' => 'elemplus-failed',
  //         '4' => 'heroicon-m-arrow-uturn-left',
  //         '5' => 'ri-refund-2-fill'


   //      })

                ->color(fn (string $state): string => $this->getStatusColors()[$state] ?? 'gray'),

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
                Action::make('pdf')
                ->label('Invois')
                ->color('danger')
                ->visible(fn(): bool => auth()->user()->can('view-any User'))
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

               Action::make('bayar')
                       ->icon('heroicon-m-credit-card')
                       ->color('danger')
                       ->visible(fn(): bool => auth()->user()->can('view-any User'))
                     // ->visible(fn () => in_array(auth()->user()->role_id, [1, 5]))
                       ->url(fn (ReportClass $pay): string => route('toyyibpay.createBill',$pay)),

              Action::make('sunting')
                    ->icon('heroicon-o-pencil-square')
                  //  ->visible(fn () => in_array(auth()->user()->role_id, [1, 5]))
                    ->visible(fn(): bool => auth()->user()->can('view-any User'))
                    ->fillForm(fn (ReportClass $record): array => [
                   'status' => $record->status,
                   'receipt' => $record->receipt,
                   'note' => $record->note,
                   //'receipt' => $record->receipt,
                      ])
                       ->form([
                           Select::make('status')
                               ->label('Status')
                               ->options($this->getStatusOptions()),
                              // ->required(),
                              // FileUpload::make('receipt')

                            //   ->image()
                            //   ->label('Resit')
                            //   ->required()

                         //      ->disk('public')
                         //      ->directory('images')
                         //      ->visibility('public')
                               //->storeFiles(false)
                          //      ->downloadable()
                         //       ->loadingIndicatorPosition('left')
                         //       ->panelAspectRatio('2:1')
                         //       ->panelLayout('integrated')
                         //       ->removeUploadedFileButtonPosition('right')
                         //       ->uploadButtonPosition('left')
                        //        ->uploadProgressIndicatorPosition('left'),
                                TextInput::make('note')
                                ->label('Nota')

                       ])
                       ->action(function (array $data, ReportClass $record): void {
                        $record->status = $data['status'];
                     //   $record->receipt = $data['receipt'];
                        $record->note = $data['note'];


                        $record->save();
                       })
            ])
            ->groupedBulkActions([

                    ExportBulkAction::make()
                    ->label('Eksport'),
                    //Tables\Actions\DeleteBulkAction::make(),
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
