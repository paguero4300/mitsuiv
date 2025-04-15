<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\NotificationChannel;
use App\Models\NotificationSetting;
use Illuminate\Support\Facades\DB;

class EnableEmailNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:enable-email-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica y habilita las notificaciones por email para pruebas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Verificando configuración de notificaciones por email...');
        
        try {
            DB::beginTransaction();
            
            // 1. Verificar si existe el canal de email
            $emailChannel = NotificationChannel::where('channel_type', 'email')->first();
            
            if (!$emailChannel) {
                $this->warn('Canal de email no encontrado. Creando...');
                $emailChannel = NotificationChannel::create([
                    'channel_type' => 'email',
                    'is_enabled' => true
                ]);
                $this->info('Canal de email creado exitosamente.');
            } else {
                $this->info('Canal de email encontrado.');
                
                // Activar si está desactivado
                if (!$emailChannel->is_enabled) {
                    $emailChannel->is_enabled = true;
                    $emailChannel->save();
                    $this->info('Canal de email activado.');
                } else {
                    $this->info('Canal de email ya está activado.');
                }
            }
            
            // 2. Verificar si existe la configuración para revendedores y evento nueva_subasta
            $notificationSetting = NotificationSetting::where('role_type', 'revendedor')
                ->where('event_type', 'nueva_subasta')
                ->where('channel_id', $emailChannel->id)
                ->first();
                
            if (!$notificationSetting) {
                $this->warn('Configuración de notificación nueva_subasta por email para revendedores no encontrada. Creando...');
                $notificationSetting = NotificationSetting::create([
                    'role_type' => 'revendedor',
                    'event_type' => 'nueva_subasta',
                    'channel_id' => $emailChannel->id,
                    'is_enabled' => true
                ]);
                $this->info('Configuración de notificación creada exitosamente.');
            } else {
                $this->info('Configuración de notificación encontrada.');
                
                // Activar si está desactivada
                if (!$notificationSetting->is_enabled) {
                    $notificationSetting->is_enabled = true;
                    $notificationSetting->save();
                    $this->info('Configuración de notificación activada.');
                } else {
                    $this->info('Configuración de notificación ya está activada.');
                }
            }
            
            DB::commit();
            
            $this->info('');
            $this->info('================================================');
            $this->info('CONFIGURACIÓN DE EMAIL HABILITADA EXITOSAMENTE');
            $this->info('================================================');
            $this->info('');
            $this->info('El sistema está listo para enviar notificaciones por email.');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error al configurar notificaciones por email: {$e->getMessage()}");
            
            return Command::FAILURE;
        }
    }
} 