<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TestEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test {email? : Dirección de correo de destino}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía un correo de prueba para verificar la configuración SMTP';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email') ?? 'juanpabloaguero2020@gmail.com';

        $this->info("Enviando correo de prueba a: $email");
        
        try {
            $this->info("📧 Usando configuración SMTP:");
            $this->info("Host: " . config('mail.mailers.smtp.host'));
            $this->info("Puerto: " . config('mail.mailers.smtp.port'));
            $this->info("Cifrado: " . config('mail.mailers.smtp.encryption'));
            $this->info("Usuario: " . config('mail.mailers.smtp.username'));
            
            Mail::raw('Este es un correo de prueba enviado desde la aplicación de Subastas Mitsui a las ' . now()->format('d/m/Y H:i:s'), function ($message) use ($email) {
                $message->to($email)
                    ->subject('Prueba de Configuración SMTP - Subastas Mitsui');
            });
            
            $this->info("✅ Correo de prueba enviado correctamente.");
            Log::info("Correo de prueba enviado a $email correctamente");
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error al enviar el correo: " . $e->getMessage());
            Log::error("Error al enviar correo de prueba", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
    }
} 