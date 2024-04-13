<?php

global $CFG;
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

class local_custom_service_external extends external_api
{
    /**
     * @throws coding_exception
     */
    public static function user_signup_with_confirmation_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'username' => new external_value(core_user::get_property_type('username'), 'Username'),
                'password' => new external_value(core_user::get_property_type('password'), 'Plain text password'),
                'firstname' => new external_value(core_user::get_property_type('firstname'), 'The first name(s) of the user'),
                'lastname' => new external_value(core_user::get_property_type('lastname'), 'The family name of the user'),
                'email' => new external_value(core_user::get_property_type('email'), 'A valid and unique email address'),
            )
        );
    }

    /**
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function user_signup_with_confirmation($username, $password, $firstname, $lastname, $email): array
    {
        global $DB, $CFG;

        // Check if the username already exists
        if ($DB->record_exists('user', array('username' => $username))) {
            // Generate a unique username
            $username = self::generate_unique_username($username);
        }

        // Check if the email already exists
        if ($DB->record_exists('user', array('email' => $email))) {
            return array(
                'success' => false,
                'warnings' => array('User creation failed. Email is already existed!')
            );
        }

        // Create a new user object
        $newuser = new stdClass();
        $newuser->username = $username;
        $newuser->password = hash_internal_user_password($password);
        $newuser->firstname = $firstname;
        $newuser->lastname = $lastname;
        $newuser->email = $email;
        $newuser->redirect = "http://localhost/moodle/login/index.php";
        $newuser->mnethostid = $CFG->mnet_localhost_id;
        $newuser->secret = random_string();
        $newuser->auth = $CFG->registerauth;

        // Save the new user to the database
        try {
            $newuserid = $DB->insert_record('user', $newuser);
        } catch (Exception $e) {
            return array(
                'success' => false,
                'warnings' => array('User creation failed.', $e)
            );
        }

        if ($newuserid) {
            try {
                // Send the confirmation email to the user
                send_confirmation_email($newuser);
            } catch (Exception $e) {
                return array(
                    'success' => false,
                    'warnings' => array('Failed to send confirmation email.', $e)
                );
            }
            return array(
                'success' => true,
                'warnings' => array()
            );
        } else {
            return array(
                'success' => false,
                'warnings' => array('User creation failed.')
            );
        }
    }

    /**
     * Generate a unique username by appending a number to the end of the username
     *
     * @param string $username
     * @return string
     */
    private static function generate_unique_username($username): string
    {
        global $DB;

        $i = 1;
        $new_username = $username;
        while ($DB->record_exists('user', array('username' => $new_username))) {
            $new_username = $username . $i;
            $i++;
        }

        return $new_username;
    }


    public static function user_signup_with_confirmation_returns(): external_single_structure
    {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'True if the user was created,5w false otherwise'),
                'warnings' => new external_warnings(),
            )
        );
    }

    public static function enrol_user_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'email' => new external_value(PARAM_TEXT, 'user email'),
                'firstname' => new external_value(PARAM_TEXT, 'user firstname'),
                'lastname' => new external_value(PARAM_TEXT, 'user lastname'),
                'courseid' => new external_value(PARAM_INT, 'course ID'),
                'roleid' => new external_value(PARAM_INT, 'role ID'),
                'timestart' => new external_value(PARAM_TEXT, 'optional start time', VALUE_OPTIONAL),
                'timeend' => new external_value(PARAM_TEXT, 'optional end time', VALUE_OPTIONAL)
            )
        );
    }

    public static function enrol_user($email, $firstname, $lastname, $courseid, $roleid, $timestart, $timeend): stdClass
    {
        global $DB;

        // Initialize the response structure
        $response = new stdClass();
        $response->success = false;
        $response->warnings = array();

        // Perform the enrollment process
        try {
            // Format timestart, timeend from String to Unix timestamps
            $convertedtimestampstart = self::timestamp_string_convert_to_Unix($timestart);
            $convertedtimestampend = self::timestamp_string_convert_to_Unix($timeend);

            $enrol = enrol_get_plugin('manual');
            $instances = enrol_get_instances($courseid, true);
            $user = $DB->get_record('user', array('email' => $email));
            if ($user) {
                foreach ($instances as $instance) {
                    if ($instance->enrol == 'manual') {
                        $enrol->enrol_user($instance, $user->id, $roleid, $convertedtimestampstart, $convertedtimestampend);
                        $response->success = true;
                        break;
                    }
                }
            } else {
                // User does not exist, perform user signup with confirmation
                $username = strtolower($firstname . $lastname);
                $password = bin2hex(random_bytes(5));
                error_log($password);
                $redirect = '';

                $signupResponse = self::user_signup_with_confirmation($username, $password, $firstname, $lastname, $email);

                if ($signupResponse['success']) {
                    // User signup successful, proceed with enrollment
                    $user = $DB->get_record('user', array('email' => $email));

                    foreach ($instances as $instance) {
                        if ($instance->enrol == 'manual') {
                            $enrol->enrol_user($instance, $user->id, $roleid, $convertedtimestampstart, $convertedtimestampend);
                            $response->success = true;
                            break;
                        }
                    }
                } else {
                    // User signup failed, set appropriate warnings
                    $response->warnings = $signupResponse['warnings'];
                }
            }
        } catch (Exception $e) {
            $response->warnings[] = $e->getMessage();
        }

        return $response;
    }


    public static function enrol_user_returns(): external_single_structure
    {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'True if the enrol process is completely successfully, false otherwise'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * @throws Exception
     */
    protected static function timestamp_string_convert_to_Unix(string $timestamp): int
    {
        $timezone = new DateTimeZone('Asia/Bangkok');
        $datetime = new DateTime($timestamp, $timezone);
        return $datetime->getTimestamp();
    }

    protected static function temporary_password_maail($email, $password)
    {
        $to = $email;
        $subject = "Your Temporary Password";
        $message = "Hello, your temporary password is: " . $password . "\nPlease change your password as soon as you log in.";
        $headers = "From: noreply@demoelearning.com";

        if(mail($to, $subject, $message, $headers)) {
            echo "Email sent successfully";
        } else {
            echo "Email sending failed";
        }
    }
}