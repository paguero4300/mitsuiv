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
                    ->maxFiles(20)
                    ->reorderable()
                    ->getUploadedFileNameForStorageUsing(function ($file) {
                        return 'vehicle_image_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    })
                    ->helperText('Puedes subir hasta 20 imágenes. Máximo 5MB por imagen. La primera imagen será marcada como principal automáticamente.')
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
                    ->uploadProgressIndicatorPosition('left')
                    ->columnSpanFull(),
            ])
            ->columns(1);
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
                    ->height(80)
                    ->width(140)
                    ->square()
                    ->extraImgAttributes(['class' => 'object-cover']),

                // Columna que muestra si la imagen es principal usando iconos
                IconColumn::make('is_main')
                    ->label('Principal')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                // Columna que muestra el orden de la imagen (editable)
                Tables\Columns\TextInputColumn::make('order')
                    ->label('Orden')
                    ->type('number')
                    ->sortable()
                    ->alignCenter()
                    ->rules(['integer', 'min:1', 'max:20'])
                    ->afterStateUpdated(function (VehicleImage $record, $state) {
                        // Convertir a entero
                        $newOrder = (int) $state;

                        // Si el nuevo orden es 1, marcar como principal
                        if ($newOrder === 1 && !$record->is_main) {
                            // Desmarcar todas las demás imágenes como principales
                            $record->vehicle->images()
                                ->where('id', '!=', $record->id)
                                ->update(['is_main' => false]);

                            // Marcar esta como principal
                            $record->is_main = true;
                            $record->save();
                        }

                        // Reordenar todas las imágenes
                        $this->reorderImages($record->vehicle);
                    }),
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
                        if (($currentCount + $newImages) > 20) {
                            Notification::make()
                                ->title('Límite de imágenes excedido')
                                ->body('Solo puedes tener un máximo de 20 imágenes por vehículo.')
                                ->danger()
                                ->send();
                            return;
                        }

                        try {
                            // Iniciar transacción para asegurar consistencia
                            \DB::beginTransaction();

                            $paths = is_array($data['path']) ? $data['path'] : [$data['path']];

                            // Siempre marcar la primera imagen como principal
                            $vehicle->images()->update(['is_main' => false]);

                            // Obtener el orden actual más alto
                            $maxOrder = $vehicle->images()->max('order');
                            $startOrder = $maxOrder > 0 ? $maxOrder + 1 : 1;

                            // Si no hay imágenes, la primera será orden 1
                            if ($currentCount === 0) {
                                $startOrder = 1;
                            }

                            // Procesar cada imagen
                            foreach ($paths as $index => $path) {
                                $vehicle->images()->create([
                                    'path' => $path,
                                    'order' => $startOrder + $index,
                                    'is_main' => ($index === 0), // Primera imagen siempre principal
                                ]);
                            }

                            // Reordenar todas las imágenes para asegurar secuencia correcta
                            $this->reorderImages($vehicle);

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
                // Se eliminaron los botones de 'Marcar como Principal', 'Subir Orden' y 'Bajar Orden'
                // ya que ahora se puede cambiar el orden directamente desde la columna 'Orden'
                // y la imagen con orden 1 se marca automáticamente como principal

                EditAction::make()
                    ->modalHeading('Editar imagen')
                    ->action(function (VehicleImage $record, array $data): void {
                        // Si se está cambiando el orden a 1, marcar como principal
                        if (isset($data['order']) && (int)$data['order'] === 1 && !$record->is_main) {
                            // Desmarcar todas las demás imágenes como principales
                            $record->vehicle->images()
                                ->where('id', '!=', $record->id)
                                ->update(['is_main' => false]);

                            // Marcar esta como principal
                            $data['is_main'] = true;
                        }

                        // Si es la única imagen principal, no permitir cambiar su orden de 1
                        if ($record->is_main && isset($data['order']) && (int)$data['order'] !== 1) {
                            if ($record->vehicle->images()->where('is_main', true)->count() <= 1) {
                                Notification::make()
                                    ->title('La imagen principal debe tener orden 1')
                                    ->danger()
                                    ->send();
                                return;
                            }
                            // Si hay otras imágenes, permitir cambiar el orden y desmarcar como principal
                            $data['is_main'] = false;
                        }

                        $record->update($data);

                        // Reordenar las imágenes para mantener la secuencia correcta
                        $this->reorderImages($record->vehicle);

                        Notification::make()
                            ->title('Imagen actualizada correctamente')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->modalHeading('Eliminar imagen')
                    ->modalDescription('¿Estás seguro de que deseas eliminar esta imagen? Esta acción no se puede deshacer.')
                    ->before(function (VehicleImage $record) {
                        // No permitir eliminar si es la única imagen principal
                        if ($record->is_main && $record->vehicle->images()->where('is_main', true)->count() <= 1) {
                            Notification::make()
                                ->title('No se puede eliminar la única imagen principal')
                                ->danger()
                                ->send();
                            return false;
                        }
                    }),
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

        // Si la imagen eliminada era la principal y hay más imágenes,
        // hacer la primera imagen la principal
        if ($record->is_main && $vehicle->images()->count() > 0) {
            $firstImage = $vehicle->images()->orderBy('order')->first();
            $firstImage->update(['is_main' => true, 'order' => 1]);
        }

        // Reordenar todas las imágenes
        $this->reorderImages($vehicle);
    }

    /**
     * Reordena las imágenes de un vehículo asegurando que la principal tenga orden 1
     * y respetando los órdenes asignados por el usuario
     */
    protected function reorderImages($vehicle): void
    {
        // Primero aseguramos que la imagen principal tenga orden 1
        $mainImage = $vehicle->images()->where('is_main', true)->first();
        if ($mainImage) {
            // Solo actualizar si no es ya orden 1
            if ($mainImage->order != 1) {
                $mainImage->update(['order' => 1]);
            }
        }

        // Verificar si hay imágenes con el mismo orden y resolverlo
        $allImages = $vehicle->images()->orderBy('order')->get();
        $usedOrders = [];
        $maxOrder = 1; // Empezamos con 1 para la imagen principal

        foreach ($allImages as $image) {
            // Si es la imagen principal, ya le asignamos orden 1
            if ($image->is_main) {
                $usedOrders[1] = true;
                continue;
            }

            $currentOrder = $image->order;

            // Si el orden ya está usado o es 1 (reservado para la principal)
            if (isset($usedOrders[$currentOrder]) || $currentOrder == 1) {
                // Encontrar el siguiente orden disponible
                $newOrder = empty($usedOrders) ? 2 : max(array_keys($usedOrders)) + 1;
                $image->update(['order' => $newOrder]);
                $usedOrders[$newOrder] = true;
            } else {
                // El orden no está usado, lo marcamos como usado
                $usedOrders[$currentOrder] = true;
            }

            // Actualizar el orden máximo
            $maxOrder = max($maxOrder, $image->order);
        }

        // Si no hay imagen principal pero hay imágenes, hacer que la primera sea principal
        if (!$mainImage && $vehicle->images()->count() > 0) {
            $firstImage = $vehicle->images()->orderBy('order')->first();
            if ($firstImage) {
                $firstImage->update(['is_main' => true, 'order' => 1]);

                // Reordenar las demás imágenes para evitar duplicados
                $this->reorderImages($vehicle);
            }
        }
    }
}
