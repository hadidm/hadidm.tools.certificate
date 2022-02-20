<?php

use setasign\Fpdi\Fpdi;

define('FPDF_FONTPATH',dirname(__FILE__) . "/font/");

require_once 'vendor/autoload.php';
require 'inc/config.php';
require 'inc/certMailer.php';
// require 'inc/certDompdf.php';

error_reporting(E_ALL ^ E_DEPRECATED);
ini_set('memory_limit', MEMORY_LIMIT);
setlocale(LC_ALL, LOCALE);
date_default_timezone_set('Etc/UTC');

$def_options = [
    ['f', 'folder', \GetOpt\GetOpt::REQUIRED_ARGUMENT, 'Subfolder of input and output folder'],
    ['t', 'test', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'Test Mode. Will not send email'],
    ['n', 'numberofdata', \GetOpt\GetOpt::OPTIONAL_ARGUMENT, 'Number of data that will be processed'],
    ['?', 'help', \GetOpt\GetOpt::NO_ARGUMENT, 'Show this help and quit'],
];
$getopt = new \GetOpt\GetOpt($def_options);
try {
    try {
        $getopt->process();
    } catch (Exception $exception) {
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

/**
 * Validate command options and settings.json
 */
 
if (!isset($options['f'])) {
    echo "EXIT PROCESS...\r\n";
    echo 'REQUIRE -f OPTIONS!!';
    exit;
}

$folderName = $options['f'];
$inputFolder = "input".DIRECTORY_SEPARATOR.$folderName;
$outputFolder = "output".DIRECTORY_SEPARATOR.$folderName;

$json_str = file_get_contents($inputFolder.DIRECTORY_SEPARATOR.'settings.json');
$settings = json_decode($json_str);

if(!isset($settings->parameter)) {
    echo "EXIT PROCESS...\r\n";
    echo '"parameter" in settings.json is required';
    exit;
} else {
    $required = ["template", "data", "email_col", "subject", "email_message", "replyto"];
    $miss = [];
    foreach ($required as $req) {
        if (!array_key_exists($req, $settings->parameter) || !$settings->parameter->$req) {
            array_push($miss, $req);
        }
    }
    if (count($miss) > 0) {
        echo "EXIT PROCESS...\r\n";
        echo 'these fields of "parameter" is required: '.implode(", ", $miss);
        exit;
    }
}

foreach ($settings->writer->fonts as $font) {
    if (!file_exists("font".DIRECTORY_SEPARATOR.$font.".php") || 
        !file_exists("font".DIRECTORY_SEPARATOR.$font.".z")) {
            echo "EXIT PROCESS...\r\n";
            echo 'font file not set properly';
            exit;
    }
}


/**
 * Set variables
 */

$templatePDF = $inputFolder.DIRECTORY_SEPARATOR.$settings->parameter->template;
$data_file = $inputFolder.DIRECTORY_SEPARATOR.$settings->parameter->data;
$email_col_name = $settings->parameter->email_col;
$email_subject = $settings->parameter->subject;
$email_message = $inputFolder.DIRECTORY_SEPARATOR.$settings->parameter->email_message;
if (is_file($email_message)) {
    $email_message = file_get_contents($email_message);
}

if (isset($settings->parameter->replyto)) {
    if (preg_match('/(.*);(.*@.*)/', $settings->parameter->replyto, $matches)) {
        $email_from_name = $matches[1];
        $email_from = $matches[2];
    } else {
        $email_from = $settings->parameter->replyto;
        $email_from_name = $email_from;
    }
} else {
    $email_from = MAIL_USERNAME;
    $email_from_name = MAIL_USERNAME;
}

$attchments = $settings->parameter->attachments;

$isTest = false;
if (isset($options['t'])) {
    $isTest = true;
}

$numData = 0;
if (isset($options['n'])) {
    $numData = $options['n'];
}
/**
 * Now generate!
 * 
 */
$seq_cert = 0;
$log = "";

if (!empty($data_file)) {
    if (false === is_dir($outputFolder)) {
        mkdir($outputFolder, 0777, true);
    }

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

                    $filename = $i;
                    if(isset($row[$email_col_name])) {
                        $filename = $row[$email_col_name];
                        $filename = preg_split("/[\/\\\,]/", $filename);
                        $filename = strtolower(trim($filename[0]));
                    } else if(array_key_exists('full_name', $row)) {
                        $filename = $row['full_name'];
                    }

                    // generate certificate id
                    $seq_cert++;
                    $cert_id = str_pad($seq_cert, 4, "0", STR_PAD_LEFT);
                    $cert_id = substr_replace($cert_id, " ", 2, 0);
                    $cert_id = str_replace("{{seq}}", $cert_id, $settings->writer->cert_id);
                    $row['cert_id'] = $cert_id;
    
                    $output_file = $outputFolder.DIRECTORY_SEPARATOR.$filename.'.pdf';

                    if (array_key_exists($email_col_name, $row))
                        $cur_log = $cert_id." | ".$row[$email_col_name]."\r\n";
                    else
                        $cur_log = $cert_id." | ".$row['full_name']."\r\n";

                    echo $cur_log;
                    $log .= $cur_log;
                    
                    generatePDFuseFPDI($templatePDF, $settings->writer, $output_file, $row);
                    unset($pdf);
    
                    if ($send_by_email && file_exists($output_file) && !$isTest) {
                        $email_to = $row[$email_col_name];
                        
                        // accommodate multiple email destinations for 1 recipient
                        $emails = preg_split("/[\/\\\,]/", $email_to);
                        foreach ($emails as $to) {
                            $email_body = $email_message;
                            foreach ($row as $key => $value) {
                                $email_body = preg_replace('/\{\{\s*'.$key.'\s*\}\}/', trim($value), $email_body);
                            }
                            $email_body = str_replace('{{ event_name }}', $settings->parameter->event_name, $email_body);
                            $email_body = str_replace('{{ %now% }}', strftime(DATE_FORMAT), $email_body);
        
                            $mailer->send_mail($to, $email_subject, $email_body, $email_from, $email_from_name, $output_file, $attchments);
                        }
                    }

                    if ($numData > 0 && $i == $numData - 1) {
                        break;
                    }
                    ++$i;
                }
            } catch(Exception $e) {
                echo "ERROR ".$e->getMessage()."\r\n";
                $log .= "ERROR ".$e->getMessage()."\r\n";
            }
        }

        fclose($handle);
    }

    // Write log
    $f = fopen($outputFolder.DIRECTORY_SEPARATOR.'result.txt','w');
    fwrite($f, $log);
    fclose($f);

} else {
    echo "Data input is empty\n";
    exit;
}

function generatePDFuseFPDI($templatePDF, $writer, $outPath, $data) {
    $pdf = new FPDI('L', 'pt');

    $pdf->SetTopMargin(0);
    $pdf->SetLeftMargin(0);
    $pdf->SetRightMargin(0);
    $pdf->SetAutoPageBreak(0);

    // Copy the template from the source file
    $pageCount = $pdf->setSourceFile($templatePDF);

    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
        $tplIdx = $pdf->importPage($pageNo);
        $pdf->addPage('L', $writer->pdf_size);
        $pdf->useTemplate($tplIdx);

        if ($pageNo == 1) {
            // Set font
            foreach ($writer->fonts as $font) {
                $pdf->AddFont($font, '', $font.'.php');
            }

            foreach ($writer->texts as $text) {
                WriteCell(
                    $pdf, 
                    $text->pos_x, 
                    $text->pos_y, 
                    $text->font, 
                    $text->size, 
                    $text->align, 
                    $data[$text->value_col]
                );
            }
        }
    }
    
    $pdf->Output('F', $outPath);
}

function WriteCell($pdf, $x, $y, $font_name, $font_size, $align, $text) {
    $pdf->ln($x);
    if ($y > 0)
        $pdf->Cell($y);
    $pdf->SetFont($font_name, '', $font_size);
    $pdf->Cell(0, 0, $text, 0, 1, $align);
}