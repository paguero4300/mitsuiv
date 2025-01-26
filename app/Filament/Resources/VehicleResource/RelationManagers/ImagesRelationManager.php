<?php

namespace App\Filament\Resources\VehicleResource\RelationManagers;

use App\Models\VehicleImage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;

class ImagesRelationManager extends RelationManager
{

    protected static ?string $title = 'Listado de Imagenes'; // Esto ocultará el título "Images"
    protected static string $relationship = 'images';

    // Especifica qué atributo del modelo se usará como título en formularios y mensajes
    protected static ?string $recordTitleAttribute = 'path';

    // Define la estructura del formulario para crear y editar imágenes
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Campo para subir imágenes con configuraciones específicas
                FileUpload::make('path')
                    ->label('Imagen')
                    ->image()
                    ->disk('public')
                    ->directory('vehicle-images')
                    ->required()
                    ->maxSize(5120)
                    ->maxFiles(1)
                    ->getUploadedFileNameForStorageUsing(function ($file) {
                        return 'vehicle_image_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    })
                    ->rules(['required', 'image', 'max:5120']),

                // Toggle para marcar una imagen como principal
                Toggle::make('is_main')
                    ->label('Imagen Principal')
                    ->default(false)
                    ->helperText('Marca esta imagen como la principal para la galería.')
                    ->rules(['boolean'])
                    ->dehydrated(true) // Asegura que el valor se guarde
                    ->disabled(function ($record) {
                        // Deshabilita el toggle si ya es la imagen principal
                        return $record && $record->is_main;
                    }),
            ]);
    }

    // Define la estructura y comportamiento de la tabla que muestra las imágenes
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                // Columna que muestra la miniatura de la imagen
                ImageColumn::make('path')
                    ->label('Imagen')
                    ->disk('public')
                    ->height(50)
                    ->width(50)
                    ->square()
                    ->extraImgAttributes(['class' => 'object-cover']),

                // Columna que muestra si la imagen es principal usando iconos
                IconColumn::make('is_main')
                    ->label('Principal')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                // Columna que muestra el orden de la imagen
                TextColumn::make('order')
                    ->label('Orden')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->defaultSort('order', 'asc')
            ->filters([])
            ->headerActions([
                // Acción para crear nuevas imágenes
                Tables\Actions\CreateAction::make()
                    ->label('Añadir Imagen')
                    ->action(function (array $data): void {
                        $vehicle = $this->getOwnerRecord();

                        if ($vehicle->images()->count() >= 10) {
                            Notification::make()
                                ->title('Máximo de imágenes alcanzado')
                                ->danger()
                                ->send();
                            return;
                        }

                        // Asignar el siguiente orden automáticamente
                        $data['order'] = $vehicle->images()->max('order') + 1;

                        // Si es la primera imagen, hacerla principal automáticamente
                        if ($vehicle->images()->count() === 0) {
                            $data['is_main'] = true;
                        } elseif (isset($data['is_main']) && $data['is_main']) {
                            // Si se marca como principal, desmarcar las demás
                            $vehicle->images()->update(['is_main' => false]);
                        }

                        $vehicle->images()->create($data);

                        Notification::make()
                            ->title('Imagen añadida correctamente')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading('Editar imagen')
                    ->action(function (VehicleImage $record, array $data): void {
                        // Si se está marcando como principal
                        if (isset($data['is_main']) && $data['is_main'] && !$record->is_main) {
                            // Desmarcar todas las demás imágenes como principales
                            $record->vehicle->images()
                                ->where('id', '!=', $record->id)
                                ->update(['is_main' => false]);
                        }
                        
                        // No permitir desmarcar la única imagen principal
                        if ($record->is_main && (!isset($data['is_main']) || !$data['is_main'])) {
                            if ($record->vehicle->images()->where('is_main', true)->count() <= 1) {
                                Notification::make()
                                    ->title('Debe existir al menos una imagen principal')
                                    ->danger()
                                    ->send();
                                return;
                            }
                        }

                        $record->update($data);

                        Notification::make()
                            ->title('Imagen actualizada correctamente')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->modalHeading('Eliminar imagen')
                    ->modalDescription('¿Estás seguro de que deseas eliminar esta imagen? Esta acción no se puede deshacer.'),
            ])
            ->bulkActions([
                // Acciones que se pueden aplicar a múltiples imágenes seleccionadas
                Tables\Actions\DeleteBulkAction::make()
                    ->modalDescription('¿Estás seguro de que deseas eliminar las imágenes seleccionadas? Esta acción no se puede deshacer.'),
            ]);
    }

    // Se ejecuta después de eliminar una imagen para limpiar el archivo físico
    protected function afterDelete(Model $record): void
    {
        if ($record->path) {
            Storage::disk('public')->delete($record->path);
        }

        // Reordenar las imágenes restantes
        $vehicle = $this->getOwnerRecord();
        $images = $vehicle->images()->orderBy('order')->get();
        
        foreach ($images as $index => $image) {
            $image->update(['order' => $index + 1]);
        }

        // Si la imagen eliminada era la principal y hay más imágenes,
        // hacer la primera imagen la principal
        if ($record->is_main && $images->count() > 0) {
            $images->first()->update(['is_main' => true]);
        }
    }
}
