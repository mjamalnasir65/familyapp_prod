<?php
// Editor-only PHPMailer stubs to satisfy static analyzers (e.g., Intelephense)
// Safe in production because these are not autoloaded; guards prevent redefinition.

namespace PHPMailer\PHPMailer {
    if (!class_exists('PHPMailer\\PHPMailer\\Exception')) {
        class Exception extends \Exception {}
    }
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        class PHPMailer {
            public function __construct($exceptions = null) {}
            public function isSMTP() {}
            public function __set($name, $value) {}
            public function setFrom($address, $name = '') {}
            public function addAddress($address, $name = '') {}
            public function isHTML($isHtml = true) {}
            public function send() { return false; }
            // properties used in Mailer
            public $Host;
            public $Port;
            public $SMTPAuth;
            public $Username;
            public $Password;
            public $SMTPSecure;
            public $CharSet;
            public $Subject;
            public $AltBody;
            public $Body;
        }
    }
}
