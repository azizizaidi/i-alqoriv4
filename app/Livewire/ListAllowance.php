<?php

namespace App\Livewire;

use App\Models\ReportClass;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Livewire\Component;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Blade;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
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
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Builder;





class ListAllowance extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;



    public function table(Table $table): Table



    {

        return $table

       
            ->striped()
            
            ->groups([
                    Group::make('month')
                    ->label('Bulan')
                    ->orderQueryUsing(fn (Builder $query, string $direction) => $query->orderBy('created_by_id', $direction)),
                    Group::make('created_by.name')
                    ->label('Nama')
                    ->orderQueryUsing(fn (Builder $query, string $direction) => $query->orderBy('created_by_id', $direction)),
                   // ->collapsible(),
                   // ->scopeQueryByKeyUsing(fn (Builder $query, string $key) => $query->where('month', $key)),
                   // ->groupQueryUsing(fn (Builder $query) => $query->groupBy('month')),
                ])  
             ->groupRecordsTriggerAction(
                    fn (Action $action) => $action
                        ->button()
                        ->label('Kumpulan'),
                )
            ->query(ReportClass::query()->orderBy('created_at', 'desc'))
            ->paginated([5,10, 25, 50, 100])
            ->deferLoading()
            ->columns([

                    //TextColumn::make('id')
                   // ->searchable(isIndividual: true),

                    TextColumn::make('created_by.id')
                    ->label('ID Guru')
                    ->sortable(),
                    TextColumn::make('created_by.name')
                    ->label('Nama Guru')
                    //->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                    TextColumn::make('created_at')
                    ->label('Tarikh Hantar')
                    ->sortable(),
               
                    TextColumn::make('month')
                    ->label('Bulan')
                    ->toggleable()
                    ->searchable(),

                    TextColumn::make('allowance')
                    ->label('Elaun')
                    ->currency('MYR')
                    ->searchable()
                    ->summarize(Sum::make()->money('MYR'))
                    ->toggleable(),

                    TextColumn::make('allowance_note')
                ->badge()
                ->label('Status Elaun') // Optional: Add a label for the column header
                ->formatStateUsing(fn ($state) => match ($state) {
                    'dah_bayar' => 'Dah Bayar',

                    'belum_bayar' => 'Belum Bayar',

                    'NULL'  => 'Tiada Data',

                   
               })
   //        IconColumn::make('allowance_note')
  //  ->icon(fn (string $state): string => match ($state) {
  //      'dah_bayar' => 'si-ticktick',
  //      'belum_bayar' => 'heroicon-s-table-cells',
       
  //  })
                ->color(fn (string $state): string => match ($state) {
                    'dah_bayar' => 'success',
                    'belum_bayar' => 'danger',
                   
                    'NULL' => 'gray',
                }),
                  
                  



            ])
            ->defaultGroup('created_by.name')
            //->groupsOnly()
    
            ->filters([
                SelectFilter::make('allowance_note')
                ->label('Elaun Status')
                ->options([
                    'dah_bayar' => 'Dah Bayar',

                    'belum_bayar' => 'Belum Bayar',

                  

                ]),
                SelectFilter::make('month')
                ->label('Bulan')
                ->searchable()
                ->preload()
               // ->default()
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
       
                    BulkAction::make('edit')
                    ->icon('heroicon-o-pencil')
                    ->label('Ubah')
                    ->visible(auth()->user()->hasRole(1))
                   // ->can(fn (User $record) => auth()->user()->can('edit_allowance::report'))
                    ->modalSubheading()
                    ->action(function (array $data, Collection $records): void {
                        foreach ($records as $record) {
                            if (auth()->user()->hasRole(1)) {
                                $record->allowance_note = $data['allowance_note'];
                                $record->save();
                            }
                        }
                    })
                    ->fillForm(function (Collection $records): array {
                        // Map each selected record to its 'allowance_note' value
                        $formData = [];
                        foreach ($records as $record) {
                            $formData[$record->id] = [
                                'allowance_note' => $record->allowance_note,
                            ];
                        }
                        return $formData;
                    })
                    ->form([
                   
                         Select::make('allowance_note')
                            ->label('Status')
                            ->options([
                                'dah_bayar' => 'Elaun Dah Dibayar',
                                'belum_bayar' => 'Elaun Belum Dibayar',
                            ])
                            ->required(),
                    ])


            ]);
    }

    public function render(): View
    {
        return view('livewire.list-fee');
    }

    

}
