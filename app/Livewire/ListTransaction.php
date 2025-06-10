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




class ListTransaction extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;
    use LivewireAlert;


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
                    ->whereNotIn('month', ['null', 'mar2022', 'apr2022']);
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
                    ->options([
                        0 => 'Belum Bayar',

                        1 => 'Dah Bayar',

                        2 => 'Dalam Proses',

                        3 => 'Gagal Bayar',

                     ])

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
                ->options([
                    0 => 'Belum Bayar',

                    1 => 'Dah Bayar',

                    2 => 'Dalam Proses',

                    3 => 'Gagal Bayar',

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
                ]),
             

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
