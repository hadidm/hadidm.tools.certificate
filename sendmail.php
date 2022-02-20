<?php

use setasign\Fpdi\Fpdi;

define('FPDF_FONTPATH',dirname(__FILE__) . "/font/");

require_once 'vendor/autoload.php';
require 'inc/config.php';
require 'inc/certMailer.php';
// require 'inc/certDompdf.php';    
$mailer = new CertMailer();

$email_subject = "Another Test Mail";
$email_body = "Body Mail Test";
$email_from = 'noreply@makarauiacademy.com';
$mailer->send_mail('setyawidyatmanto@gmail.com', $email_subject, $email_body, $email_from);
$mailer->send_mail('hadidxmubarak@gmail.com', $email_subject, $email_body, $email_from);
$mailer->send_mail('hadid@iamedu.co.id', $email_subject, $email_body, $email_from);
// $mailer->send_mail('test-u19pq7@experte-test.com', $email_subject, $email_body, $email_from);
die('DONE');

error_reporting(E_ALL ^ E_DEPRECATED);
ini_set('memory_limit', MEMORY_LIMIT);
setlocale(LC_ALL, LOCALE);
date_default_timezone_set('Etc/UTC');

$def_options = [
    ['f', 'folder', \GetOpt\GetOpt::REQUIRED_ARGUMENT, 'Subfolder of input and output folder'],
    ['d', 'data', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'CSV file with data to fill template', ''],
    ['e', 'email_col', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'Email column name in CSV file', 'email'],
    ['s', 'subject', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'Email subject', 'Certificate'],
    ['m', 'message', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'Email message or a path to file with message', 'Here is your certificate'],
    ['r', 'replyto', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'Reply to email'],
    ['a', 'attach', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'Additional attachment'],
    ['?', 'help', \GetOpt\GetOpt::NO_ARGUMENT, 'Show this help and quit'],
];
$getopt = new \GetOpt\GetOpt($def_options);
try {
    try {
        $getopt->process();
    } catch (Missing $exception) {
        // catch missing exceptions if help is requested
        if (!$getopt->getOption('help')) {
            throw $exception;
        }
    }
} catch (Exception $exception) {
    file_put_contents('php://stderr', $exception->getMessage().PHP_EOL);
    echo PHP_EOL.$getopt->getHelpText();
    exit;
}

if ($getopt->getOption('help')) {
    echo $getopt->getHelpText();
    exit;
}
$options = $getopt->getOptions();

if (!isset($options['f'])) {
    echo "EXIT PROCESS...\r\n";
    echo 'REQUIRE -f OPTIONS!!';
    exit;
}

$folderName = $options['f'];

$inputFolder = "input".DIRECTORY_SEPARATOR.$folderName;

// CSV Data file
$data_file = '';
if (isset($options['d'])) {
    $data_file = $inputFolder.DIRECTORY_SEPARATOR.$options['d'];
}

// Send PDF by email
$email_col_name = 'email';
$send_by_email = true;
if (isset($options['e'])) {
    $email_col_name = $options['e'];
}

$email_subject = 'Certificate of participation';
if (isset($options['s'])) {
    $email_subject = $options['s'];
}

if (isset($options['m'])) {
    $email_message = $inputFolder.DIRECTORY_SEPARATOR.$options['m'];
    if (is_file($email_message)) {
        $email_message = file_get_contents($email_message);
    }
}

if (isset($options['r'])) {
    if (preg_match('/(.*);(.*@.*)/', $options['r'], $matches)) {
        $email_from_name = $matches[1];
        $email_from = $matches[2];
    } else {
        $email_from = $options['r'];
        $email_from_name = $email_from;
    }
} else {
    $email_from = MAIL_USERNAME;
    $email_from_name = MAIL_USERNAME;
}

// Get any email attchament
$attchments = [];
if (isset($options['a'])) {
    $attchments = explode(',', $options['a']);
    for($i=0; $i<count($attchments); $i++) {
        $attchments[$i] = $inputFolder.DIRECTORY_SEPARATOR.$attchments[$i];
    }
}

if (!empty($data_file)) {
    if (false !== ($handle = fopen($data_file, 'r'))) {
        $csv_header = fgetcsv($handle, 1000, DELIMITER);
        $send_by_email = in_array($email_col_name, $csv_header);
        $i = 0;
        $mailer = new CertMailer();
        while (false !== ($data = fgetcsv($handle, 1000, DELIMITER))) {
            try {
                if (count($data) > 0) {
                    $row = [];
                    foreach ($data as $key => $value) {
                        $row[trim($csv_header[$key])] = preg_replace('/\x{FEFF}/u', '', $value);
                    }
    
                    if(array_key_exists($email_col_name, $row) && !$row[$email_col_name])
                        continue;

                    if (array_key_exists($email_col_name, $row))
                        echo $row[$email_col_name]."\r\n";
                    else
                        echo $row['full_name']."\r\n";
                        
                    $email_to = $row[$email_col_name];
                    
                    // accommodate multiple email destinations for 1 recipient
                    $emails = preg_split("/[\/\\\,]/", $email_to);
                    foreach ($emails as $to) {
                        $email_body = $email_message;
                        foreach ($row as $key => $value) {
                            $email_body = preg_replace('/\{\{\s*'.$key.'\s*\}\}/', trim($value), $email_body);
                        }
                        $email_body = str_replace('{{ %now% }}', strftime(DATE_FORMAT), $email_body);
    
                        $mailer->send_mail($to, $email_subject, $email_body, $email_from, $email_from_name, '', $attchments);
                    }
                    ++$i;
                }
            } catch(Exception $e) {
                echo "error at $i loop. ".$e->getMessage();
            }
        }

        fclose($handle);
    }
} else {
    echo "Data input is empty\n";
    exit;
}