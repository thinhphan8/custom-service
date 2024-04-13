<?php

defined('MOODLE_INTERNAL') || die();
$functions = array(
    'local_custom_service_user_signup_with_confirmation' => array(
        'classname'   => 'local_custom_service_external',
        'methodname'  => 'user_signup_with_confirmation',
        'classpath' => 'local/custom_service/externallib.php',
        'description' => 'Adds a new user (pendingto be confirmed) in the site.',
        'type'        => 'write',
        'ajax'          => true,
        'loginrequired' => false,
    ),
    'local_custom_service_enrol_user' => array(
        'classname'   => 'local_custom_service_external',
        'methodname'  => 'enrol_user',
        'classpath' => 'local/custom_service/externallib.php',
        'description' => 'Enrol a user to a course',
        'type'        => 'write',
        'ajax'          => true,
        'loginrequired' => false,
    ),
);

$services = array(
    'Demo Custom Services' => array(
        'functions' => array(
            'local_custom_service_user_signup_with_confirmation',
            'local_custom_service_enrol_user',
        ),
        'restrictedusers' => 0,
        'enabled' => 1,
    )
);