<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed'   => 'Ces identifiants ne correspondent pas à nos enregistrements',
    'throttle' => 'Trop de tentatives de connexion. Veuillez essayer de nouveau dans :seconds secondes.',
    'social_account_not_found' => 'Aucun compte trouvé pour ce compte :provider. Veuillez vous inscrire d\'abord.',

    // Activation items
    'sentEmail'        => 'Nous avons envoyé un email à :email.',
    'clickInEmail'     => 'Cliquez sur le lien pour activer votre compte.',
    'anEmailWasSent'   => 'Un email a été expédié à :email le :date.',
    'clickHereResend'  => 'Cliquez pour réexpédier le mail.',
    'successActivated' => 'Merci, votre compte a été activé.',
    'unsuccessful'     => 'Votre compte n\'a pas pu être activé. Veuillez réessayer.',
    'notCreated'       => 'Votre compte n\'a pas pu être créé. Veuillez réessayer.',
    'tooManyEmails'    => 'Trop d\'emails d\'activation ont été envoyés à :email.<br>Veuillez réessayer dans <span class="label label-danger">:hours heures</span>.',
    'regThanks'        => 'Merci de vous être enregistré, ',
    'invalidToken'     => 'Token de validation erroné. ',
    'activationSent'   => 'Email d\'activation expédié. ',
    'alreadyActivated' => 'Compte activé. ',

    // Labels
    'whoops'          => 'Ooops! ',
    'someProblems'    => 'Il y a eu des problèmes avec les infos saisies.',
    'email'           => 'Adresse mail',
    'password'        => 'Mot de passe',
    'rememberMe'      => ' Se souvenir de moi',
    'login'           => 'Connexion',
    'forgot'          => 'Mot de passe oublié ?',
    'forgot_message'  => 'Problème de mot de passe ?',
    'name'            => 'Nom utilisateur',
    'first_name'      => 'Prénom',
    'last_name'       => 'Nom',
    'confirmPassword' => 'Confirmez mot de passe',
    'register'        => 'S\'enregistrer',

    // Placeholders
    'ph_name'          => 'Nom utilisateur',
    'ph_email'         => 'Adresse mail',
    'ph_firstname'     => 'Prénom',
    'ph_lastname'      => 'Nom',
    'ph_password'      => 'Mot de passe',
    'ph_password_conf' => 'Confirmez mot de passe',

    'reset_email_heading' => 'Réinitialiser votre mot de passe',
    'reset_email_body'    => ':project_name a reçu une demande de réinitialisation de votre mot de passe. Si vous n\'êtes pas à l\'origine de cette demande, veuillez ignorer cet e-mail. Sinon, cliquez sur le bouton ci-dessous pour réinitialiser votre mot de passe.',
    'reset_email_action'  => 'Réinitialiser le mot de passe',

    'password_reset_success'  => 'Votre mot de passe a été réinitialisé',
    'password_min_length'     => 'Votre mot de passe doit contenir au moins huit caractères',
    'passwords_do_not_match'  => 'Les mots de passe ne correspondent pas',
    'reset_successful'        => 'Réinitialisation réussie',
    'continue_to'             => 'Continuer vers',

    // User flash messages
    'sendResetLink' => 'Envoyer un lien de réinitialisation',
    'resetPassword' => 'Réinitialiser le mot de passe',
    'loggedIn'      => 'Vous êtes connecté',
    'sessionExpired'=> 'Votre session semble avoir expiré. Veuillez réessayer.',

    // email links
    'pleaseActivate'    => 'Activez votre compte SVP.',
    'clickHereReset'    => 'Cliquer pour réinitilaiser le mot de passe : ',
    'clickHereActivate' => 'Cliquez ici pour activer votre compte : ',

    // Validators
    'userNameTaken'    => 'Nom utilisateur déjà pris',
    'userNameRequired' => 'Nom utilisateur nécessaire',
    'fNameRequired'    => 'Prénom nécessaire',
    'lNameRequired'    => 'Nom nécessaire',
    'emailRequired'    => 'Adresse mail nécessaire',
    'emailInvalid'     => 'Adresse mail non valide',
    'passwordRequired' => 'Mot de passe nécessaire',
    'PasswordMin'      => 'Le mot de passe doit comporter au moins 6 caractères',
    'PasswordMax'      => 'Le mot de passe doit comporter moins de 20 caractères',
    'captchaRequire'   => 'Merci d\utiliser le Captcha',
    'CaptchaWrong'     => 'Captcha incorrect, essayez à nouveau.',
    'roleRequired'     => 'Rôle utilisateur nécesaire.',

    // Misc UI
    'error_title'  => 'Erreur',
    'continue'     => 'Continuer',
    'please_wait'  => 'Veuillez patienter...',

    // Invalid token
    'no_matching_token' => 'Aucun jeton correspondant trouvé',
    'invalid_token_hint' => 'Ce lien a peut-être expiré ou a déjà été utilisé. Veuillez demander une nouvelle réinitialisation de mot de passe.',

];
