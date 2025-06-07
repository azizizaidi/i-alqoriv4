<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClassNameResource\Pages;
use App\Filament\Resources\ClassNameResource\RelationManagers;
use App\Models\ClassName;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;

class ClassNameResource extends Resource
{
    protected static ?string $model = ClassName::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                ->label('Nama'),
                TextColumn::make('feeperhour')
                ->formatStateUsing(fn ($state) => 'RM ' . number_format($state, 2))
                ->label('Yuran Per Jam'),
                TextColumn::make('allowanceperhour')
                ->formatStateUsing(fn ($state) => 'RM ' . number_format($state, 2))
                ->label('Elaun Per Jam'),
                TextColumn::make('created_at')
                ->label('Tarikh Dibuat'),
                TextColumn::make('updated_at')
                ->label('Tarikh Dikemaskini'),
                TextColumn::make('deleted_at')
                ->label('Tarikh Dipadam'),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClassNames::route('/'),
            'create' => Pages\CreateClassName::route('/create'),
            'edit' => Pages\EditClassName::route('/{record}/edit'),
        ];
    }

    public static function getLabel(): ?string
    {
        $locale = app()->getLocale();

        if($locale == 'ms'){
            return "Nama Kelas";
        }
        else
           return "Nama Kelas";
    }
}
