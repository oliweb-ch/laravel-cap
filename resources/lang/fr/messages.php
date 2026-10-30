<?php

return [
    'validation_failed' => 'La vérification :attribute a échoué. Veuillez réessayer.',
    'middleware_failed'  => 'La vérification Cap a échoué.',

    'alert_unreachable_subject'    => 'Serveur Cap inaccessible — :site',
    'alert_unreachable_greeting'   => 'Le serveur de vérification Cap est inaccessible.',
    'alert_unreachable_endpoint'   => 'Endpoint : :endpoint',
    'alert_unreachable_error'      => 'Erreur : :message',
    'alert_unreachable_failopen'   => '⚠ Le mode fail-open est ACTIVÉ : la protection CAPTCHA est actuellement INACTIVE.',
    'alert_unreachable_failclosed' => 'Le mode fail-open est désactivé : les requêtes nécessitant le CAPTCHA sont rejetées.',
    'alert_misconfigured_subject'  => 'Mauvaise configuration Cap — :site',
    'alert_misconfigured_greeting' => 'Le serveur Cap a retourné une erreur HTTP inattendue, ce qui indique généralement un problème de configuration (secret ou clé de site invalide).',
    'alert_misconfigured_endpoint' => 'Endpoint : :endpoint',
    'alert_misconfigured_status'   => 'Code HTTP : :code',
];