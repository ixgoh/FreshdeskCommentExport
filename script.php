<?php

/**
 * script.php
 *
 * Get and save Freshdesk ticket's comments.
 *
 * @author    Goh Ie Xiann
 * @copyright 2020 ixgoh
 * @license   proprietary
 * @version   1.0.0
 *
 */

require __DIR__ . '/vendor/autoload.php';

use Curl\Curl;
use League\Csv\CannotInsertRecord;
use League\Csv\Reader;
use League\Csv\Writer;

/**
 * Curl to perform HTTP GET requests
 *
 * @param int    $ticketId Freshdesk Ticket ID
 * @param string $username Freshdesk Username
 * @param string $password Freshdesk Password
 * @param string $domain   Freshdesk Domain
 * @return mixed
 * @throws ErrorException
 */
function curl($ticketId, $username, $password, $domain)
{
    $curl = new Curl();
    $ca = __DIR__ . "/curl-ca-bundle.crt";

    $curl->setOpt(CURLOPT_RETURNTRANSFER, true);
    $curl->setOpt(CURLOPT_ENCODING, "");
    $curl->setOpt(CURLOPT_MAXREDIRS, 10);
    $curl->setOpt(CURLOPT_TIMEOUT, 0);
    $curl->setOpt(CURLOPT_FOLLOWLOCATION, true);
    $curl->setOpt(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    $curl->setOpt(CURLOPT_CAINFO, $ca);
    $curl->setOpt(CURLOPT_HTTPHEADER, array(
        "Content-Type: application/x-www-form-urlencoded"
    ));
    $curl->setBasicAuthentication($username, $password);
    $curl->get("https://$domain.freshdesk.com/api/v2/tickets/$ticketId/conversations");
    $curl->close();

    $data = json_decode($curl->response);

    return $data;
}

/**
 * Process all comments found in ticket
 *
 * @param array $data All comments in Ticket
 * @return array
 */
function getIndividualComments($data) {
    $comments = array();

    if (sizeof($data) == 0) {
        array_push($comments, "");
    } else {
        for ($i = 0; $i < sizeof($data); $i++) {
            array_push($comments, $data[$i]->body_text);
        }
    }

    return $comments;
}

/**
 * Initialize .csv file for storage
 *
 * @return Writer
 */
function prepareCsv(){
    $header = ['Ticket ID', 'Comments'];

    $csv = Writer::createFromString();

    try {
        $csv->insertOne($header);
    } catch (CannotInsertRecord $exception) {
        echo $exception->getMessage();
    }

    return $csv;
}

/**
 * Insert comments into .csv file
 *
 * @param int   $id       Ticket ID
 * @param array $comments Comments in tickets
 */
function insertCommentsCsv($id, $comments) {
    global $csv;

    $record = array();

    foreach ($comments as $comment) {
        array_push($record, array($id, $comment));
    }

    $csv->insertAll($record);
}

/**
 * Initiate download sequence
 */
function downloadCsv() {
    global $csv;

    date_default_timezone_set('Asia/Kuala_Lumpur');

    $filename = "FreshdeskComments - " . date("Y-m-d H-i-s");
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Description: File Transfer');
    header("Content-Disposition: attachment; filename=$filename.csv");

    $reader = Reader::createFromString($csv->getContent());
    $reader->output();
    exit();
}

/**
 * Prepares execution of script
 *
 * @param int    $id       Ticket ID
 * @param string $username Freshdesk Username
 * @param string $password Freshdesk Password
 * @param string $domain   Freshdesk Domain
 */
function prepExec($id, $username, $password, $domain) {
    try {
        $data = curl($id, $username, $password, $domain);
        $comments = getIndividualComments($data);
        insertCommentsCsv($id, $comments);

    } catch (ErrorException $exception) {
        echo $exception->getMessage();
    }
}

/**
 * Main
 */
$csv = prepareCsv();

$authMode = $_POST['loginMode'];
$domain = $_POST['domain'];

if ($authMode == "apiLogin"){
    $username = $_POST['apiKey'];
    $password = "";
} else if ($authMode == "emailLogin") {
    $username = $_POST['id'];
    $password = $_POST['password'];
}

if ($_POST['genOptions'] == "sequential") {
    for ($id = $_POST['minTicket']; $id <= $_POST['maxTicket']; $id++) {
        prepExec($id, $username, $password, $domain);
    }

    downloadCsv();
} elseif ($_POST['genOptions'] == "random") {
    $randomIds = array();

    for ($i = 1; $i <= $_POST['generateNum']; $i++) {
        $generatedNum = rand($_POST['minTicket'], $_POST['maxTicket']);

        while (in_array($generatedNum, $randomIds)) {
            $generatedNum = rand($_POST['minTicket'], $_POST['maxTicket']);
        }

        array_push($randomIds, $generatedNum);
        sort($randomIds);
    }

    foreach ($randomIds as $randomId) {
        prepExec($randomId, $username, $password, $domain);
    }

    downloadCsv();
}
