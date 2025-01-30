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
use Illuminate\Support\Facades\DB;

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
                FileUpload::make('path')
                    ->label('Imágenes')
                    ->image()
                    ->multiple()
                    ->disk('public')
                    ->directory('vehicle-images')
                    ->required()
                    ->maxSize(5120)
                    ->maxFiles(10)
                    ->reorderable()
                    ->getUploadedFileNameForStorageUsing(function ($file) {
                        return 'vehicle_image_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    })
                    ->helperText('Puedes subir hasta 10 imágenes. Máximo 5MB por imagen.')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->imagePreviewHeight('100')
                    ->loadingIndicatorPosition('left')
                    ->panelLayout('grid')
                    ->imageResizeMode('cover')
                    ->imageCropAspectRatio('16:9')
                    ->imageResizeTargetWidth('1920')
                    ->imageResizeTargetHeight('1080')
                    ->removeUploadedFileButtonPosition('right')
                    ->uploadButtonPosition('left')
                    ->uploadProgressIndicatorPosition('left'),

                Toggle::make('is_main')
                    ->label('Marcar Primera Imagen como Principal')
                    ->default(false)
                    ->helperText('Si seleccionas esta opción, la primera imagen se marcará como principal.')
                    ->hidden(function ($record) {
                        return $record !== null;
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
                Tables\Actions\CreateAction::make()
                    ->label('Añadir Imágenes')
                    ->action(function (array $data): void {
                        $vehicle = $this->getOwnerRecord();
                        $currentCount = $vehicle->images()->count();
                        $newImages = is_array($data['path']) ? count($data['path']) : 1;
                        
                        // Verificar límite total de imágenes
                        if (($currentCount + $newImages) > 10) {
                            Notification::make()
                                ->title('Límite de imágenes excedido')
                                ->body('Solo puedes tener un máximo de 10 imágenes por vehículo.')
                                ->danger()
                                ->send();
                            return;
                        }

                        try {
                            // Iniciar transacción para asegurar consistencia
                            \DB::beginTransaction();

                            $paths = is_array($data['path']) ? $data['path'] : [$data['path']];
                            $nextOrder = $vehicle->images()->max('order') + 1;
                            $makeFirstMain = $data['is_main'] ?? false;

                            // Si es la primera imagen del vehículo, forzar que sea principal
                            if ($currentCount === 0) {
                                $makeFirstMain = true;
                            }

                            // Si se va a marcar una nueva imagen como principal, desmarcar las existentes
                            if ($makeFirstMain) {
                                $vehicle->images()->update(['is_main' => false]);
                            }

                            // Procesar cada imagen
                            foreach ($paths as $index => $path) {
                                $vehicle->images()->create([
                                    'path' => $path,
                                    'order' => $nextOrder + $index,
                                    'is_main' => ($index === 0 && $makeFirstMain),
                                ]);
                            }

                            \DB::commit();

                            Notification::make()
                                ->title($newImages > 1 ? 'Imágenes añadidas correctamente' : 'Imagen añadida correctamente')
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            \DB::rollBack();
                            
                            Notification::make()
                                ->title('Error al guardar las imágenes')
                                ->body('Ocurrió un error al procesar las imágenes. Por favor, intenta nuevamente.')
                                ->danger()
                                ->send();
                        }
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
