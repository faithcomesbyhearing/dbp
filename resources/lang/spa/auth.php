<?php

return [

    'failed' => 'Las credenciales introducidas son incorrectas.',
    'throttle' => 'Demasiados intentos de acceso. Inténtelo de nuevo en :seconds segundos.',
    'social_account_not_found' => 'No se encontró una cuenta para esta cuenta de :provider. Por favor, regístrese primero.',

    // Activation items
    'sentEmail'        => 'Hemos enviado un correo electrónico a :email.',
    'clickInEmail'     => 'Haga clic en el enlace del correo para activar su cuenta.',
    'anEmailWasSent'   => 'Se envió un correo electrónico a :email el :date.',
    'clickHereResend'  => 'Haga clic aquí para reenviar el correo.',
    'successActivated' => 'Su cuenta ha sido activada exitosamente.',
    'unsuccessful'     => 'No se pudo activar su cuenta; por favor intente de nuevo.',
    'notCreated'       => 'No se pudo crear su cuenta; por favor intente de nuevo.',
    'tooManyEmails'    => 'Se han enviado demasiados correos de activación a :email. <br />Intente de nuevo en <span class="label label-danger">:hours horas</span>.',
    'regThanks'        => 'Gracias por registrarse, ',
    'invalidToken'     => 'Token de activación no válido. ',
    'activationSent'   => 'Correo de activación enviado. ',
    'alreadyActivated' => 'Ya está activado. ',

    // Labels
    'whoops'          => '¡Ups! ',
    'someProblems'    => 'Hubo algunos problemas con su información.',
    'email'           => 'Correo electrónico',
    'password'        => 'Contraseña',
    'rememberMe'      => ' Recordarme',
    'login'           => 'Iniciar sesión',
    'forgot'          => '¿Olvidó su contraseña?',
    'forgot_message'  => '¿Problemas con la contraseña?',
    'name'            => 'Nombre de usuario',
    'first_name'      => 'Nombre',
    'last_name'       => 'Apellido',
    'confirmPassword' => 'Confirmar contraseña',
    'register'        => 'Registrarse',

    // Placeholders
    'ph_name'          => 'Nombre de usuario',
    'ph_email'         => 'Correo electrónico',
    'ph_firstname'     => 'Nombre',
    'ph_lastname'      => 'Apellido',
    'ph_password'      => 'Contraseña',
    'ph_password_conf' => 'Confirmar contraseña',

    'reset_email_heading' => 'Restablecer su contraseña',
    'reset_email_body'    => ':project_name recibió una solicitud para restablecer su contraseña. Si usted no realizó esta solicitud, ignore este correo. De lo contrario, haga clic en el botón de abajo para restablecer su contraseña.',
    'reset_email_action'  => 'Restablecer contraseña',

    'password_reset_success'  => 'Su contraseña ha sido restablecida',
    'password_min_length'     => 'Su contraseña debe tener al menos ocho caracteres',
    'passwords_do_not_match'  => 'Las contraseñas no coinciden',
    'reset_successful'        => 'Restablecimiento exitoso',
    'continue_to'             => 'Continuar a',

    // User flash messages
    'sendResetLink' => 'Enviar enlace de restablecimiento',
    'resetPassword' => 'Restablecer contraseña',
    'loggedIn'      => '¡Ha iniciado sesión!',
    'sessionExpired'=> 'Tu sesión expiró. Por favor, intenta de nuevo.',

    // email links
    'pleaseActivate'    => 'Por favor active su cuenta.',
    'clickHereReset'    => 'Haga clic aquí para restablecer su contraseña: ',
    'clickHereActivate' => 'Haga clic aquí para activar su cuenta: ',

    // Validators
    'userNameTaken'    => 'El nombre de usuario ya está en uso',
    'userNameRequired' => 'Se requiere nombre de usuario',
    'fNameRequired'    => 'Se requiere el nombre',
    'lNameRequired'    => 'Se requiere el apellido',
    'emailRequired'    => 'Se requiere el correo electrónico',
    'emailInvalid'     => 'El correo electrónico no es válido',
    'passwordRequired' => 'Se requiere la contraseña',
    'PasswordMin'      => 'La contraseña debe tener al menos 6 caracteres',
    'PasswordMax'      => 'La longitud máxima de la contraseña es de 20 caracteres',
    'captchaRequire'   => 'Se requiere el captcha',
    'CaptchaWrong'     => 'Captcha incorrecto, intente de nuevo.',
    'roleRequired'     => 'Se requiere el rol de usuario.',

    // Misc UI
    'error_title'  => 'Error',
    'continue'     => 'Continuar',
    'please_wait'  => 'Por favor espere...',

    // Invalid token
    'no_matching_token' => 'No se encontró un token válido',
    'invalid_token_hint' => 'Este enlace puede haber expirado o ya fue utilizado. Por favor, solicite un nuevo restablecimiento de contraseña.',

];
