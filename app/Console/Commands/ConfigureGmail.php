<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class ConfigureGmail extends Command
{
    protected $signature = 'security:configure-gmail';
    protected $description = 'Configurar Gmail localmente con contraseña oculta; comprobar conexión antes de guardar';

    public function handle(): int
    {
        $path = base_path('.env');
        if (!is_file($path) || !is_writable($path)) {
            $this->error('El archivo .env no está disponible para escritura.');
            return self::FAILURE;
        }
        $email = trim((string)$this->ask('Correo Gmail que enviará los mensajes'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with(strtolower($email), '@gmail.com')) {
            $this->error('Introduce una dirección Gmail válida.');
            return self::FAILURE;
        }
        $this->line('Usa la contraseña de aplicación de Google, no tu contraseña habitual. No aparecerá en pantalla.');
        $password = str_replace(' ', '', (string)$this->secret('Contraseña de aplicación', false));
        if (!preg_match('/\A[a-zA-Z0-9]{16}\z/', $password)) {
            $this->error('Se esperaba una contraseña de aplicación de 16 caracteres. No se guardó ningún cambio.');
            return self::FAILURE;
        }
        try {
            $transport = new EsmtpTransport('smtp.gmail.com', 587, false);
            $transport->setAutoTls(true)->setRequireTls(true)->setUsername($email)->setPassword($password);
            $transport->getStream()->setTimeout(15);
            $transport->start();
            $transport->stop();
        } catch (\Throwable) {
            $this->error('No se pudo autenticar con Gmail. Revisa la contraseña de aplicación y la conexión. No se guardó ningún cambio.');
            return self::FAILURE;
        }
        $content = file_get_contents($path);
        $values = ['MAIL_MAILER'=>'smtp', 'MAIL_SCHEME'=>'smtp', 'MAIL_URL'=>'null',
            'MAIL_HOST'=>'smtp.gmail.com', 'MAIL_PORT'=>'587', 'MAIL_USERNAME'=>$email,
            'MAIL_PASSWORD'=>$password, 'MAIL_FROM_ADDRESS'=>$email, 'MAIL_FROM_NAME'=>'"Parkeo"',
            'SECURITY_ACCESS_MAIL_READY'=>'true'];
        foreach ($values as $key=>$value) {
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $line = $key.'='.$value;
            $content = preg_match($pattern, $content) ? preg_replace_callback($pattern, fn()=>$line, $content) : rtrim($content)."\n".$line."\n";
        }
        if (file_put_contents($path, $content, LOCK_EX) === false) {
            $this->error('No se pudo guardar .env.');
            return self::FAILURE;
        }
        $this->call('config:clear');
        $this->info('Gmail aceptó la conexión y se guardó la configuración. Cierra sesión y vuelve a entrar para recibir el código.');
        $this->line('No se envió un mensaje de prueba; la recepción se comprueba al iniciar sesión.');
        return self::SUCCESS;
    }
}
