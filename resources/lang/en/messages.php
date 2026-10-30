<?php

return [
    'validation_failed' => 'The :attribute verification failed. Please try again.',
    'middleware_failed'  => 'Cap verification failed.',

    'alert_unreachable_subject'    => 'Cap server unreachable — :site',
    'alert_unreachable_greeting'   => 'The Cap verification server could not be reached.',
    'alert_unreachable_endpoint'   => 'Endpoint: :endpoint',
    'alert_unreachable_error'      => 'Error: :message',
    'alert_unreachable_failopen'   => '⚠ Fail-open is ENABLED: CAPTCHA protection is currently INACTIVE.',
    'alert_unreachable_failclosed' => 'Fail-open is disabled: requests requiring CAPTCHA are being rejected.',
    'alert_misconfigured_subject'  => 'Cap server misconfiguration — :site',
    'alert_misconfigured_greeting' => 'The Cap server returned an unexpected HTTP error, which usually indicates a configuration problem (invalid secret or site key).',
    'alert_misconfigured_endpoint' => 'Endpoint: :endpoint',
    'alert_misconfigured_status'   => 'HTTP status: :code',
];