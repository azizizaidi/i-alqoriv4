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
use App\Traits\HasMonthOptions;





class ListAllowance extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;
    use HasMonthOptions;

    // Constants for allowance note options
    const ALLOWANCE_NOTE_PAID = 'dah_bayar';
    const ALLOWANCE_NOTE_UNPAID = 'belum_bayar';

    /**
     * Get allowance note options
     *
     * @return array
     */
    protected function getAllowanceNoteOptions(): array
    {
        return [
            self::ALLOWANCE_NOTE_PAID => 'Elaun Dah Dibayar',
            self::ALLOWANCE_NOTE_UNPAID => 'Elaun Belum Dibayar',
        ];
    }

    // Method generateMonthOptions() sekarang dari HasMonthOptions trait



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
                    ->summarize(Sum::make()->formatStateUsing(fn ($state) => 'RM ' . number_format($state, 2)))
                    ->toggleable(),

                    TextColumn::make('allowance_note')
                ->badge()
                ->label('Status Elaun') // Optional: Add a label for the column header
                ->formatStateUsing(fn ($state) => match ($state) {
                    self::ALLOWANCE_NOTE_PAID => 'Dah Bayar',
                    self::ALLOWANCE_NOTE_UNPAID => 'Belum Bayar',
                    'NULL'  => 'Tiada Data',
               })
   //        IconColumn::make('allowance_note')
  //  ->icon(fn (string $state): string => match ($state) {
  //      'dah_bayar' => 'si-ticktick',
  //      'belum_bayar' => 'heroicon-s-table-cells',

  //  })
                ->color(fn (string $state): string => match ($state) {
                    self::ALLOWANCE_NOTE_PAID => 'success',
                    self::ALLOWANCE_NOTE_UNPAID => 'danger',
                    'NULL' => 'gray',
                }),





            ])
            ->defaultGroup('created_by.name')
            //->groupsOnly()

            ->filters([
                SelectFilter::make('allowance_note')
                ->label('Elaun Status')
                ->options([
                    self::ALLOWANCE_NOTE_PAID => 'Dah Bayar',
                    self::ALLOWANCE_NOTE_UNPAID => 'Belum Bayar',
                ]),
                SelectFilter::make('month')
                ->label('Bulan')
                ->searchable()
                ->preload()
               // ->default()
                ->options($this->generateMonthOptions()),


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
                            ->options($this->getAllowanceNoteOptions())
                            ->required(),
                    ])


            ]);
    }

    public function render(): View
    {
        return view('livewire.list-fee');
    }



}
