<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;

include(__DIR__ . "/../vendor/autoload.php");

const MAX_DONATIONS = 5;
const MAX_DONATION_AMOUNT = 30000;

error_reporting(E_ALL);
ini_set('display_errors', 1);

//var_dump(empty($_FILES));
//die();

if (empty($_FILES)) {
    ?>
    <form enctype="multipart/form-data" method="POST">
        <label>Donatori</label>
        <input type="file" name="donors" />
        <label>Prosvetari</label>
        <input type="file" name="receivers" />

        <input type="submit" value="Submit" name="" />
    </form>
    <?php
} else {
    $donorsFilePath = __DIR__ . '/../donatori.xlsx';
    $receiversFilePath = __DIR__ . '/../prosvetari.xlsx';
    if (!move_uploaded_file($_FILES["donors"]["tmp_name"], $donorsFilePath)) {
        die('error uploading files');
    }
    if (!move_uploaded_file($_FILES["receivers"]["tmp_name"], $receiversFilePath)) {
        die('error uploading files');
    }

    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
    $reader->setReadDataOnly(true);
    $donorExcel = $reader->load($donorsFilePath);
    $receiverExcel = $reader->load($receiversFilePath);

    $donors = $donorExcel->getSheet($donorExcel->getFirstSheetIndex())->toArray();
    $receivers = $receiverExcel->getSheet($receiverExcel->getFirstSheetIndex())->toArray();

    $donorsList = [];
    foreach ($donors as $donor) {
        $donorsList[] = [
            'amount' => (int) $donor[3],
            'donorName' => $donor[0],
            'donorEmail' => $donor[1],
        ];
    }

    $receiversList = [];
    foreach ($receivers as $receiver) {
        $receiversList[] = [
            'receiverName' => $receiver[0],
            'amount' => (int) $receiver[3],
            'accountNumber' => $receiver[1],
        ];
    }

//var_dump($donorsList);
//var_dump($receivers);
//var_dump($receiversList);
//die();

// Sort in descending order based on amounts
    usort($donorsList, function ($a, $b) {
        return $b["amount"] <=> $a["amount"];
    });
    usort($receiversList, function ($a, $b) {
        return $b["amount"] <=> $a["amount"];
    });

    $result = [];
    $receiversIncomplete = [];
    $donorCount = array_fill(0, count($donorsList), 0);
    foreach ($receiversList as $receiver) {
        $remainingAmount = $receiver["amount"];
        $i = 0;
        // @TODO test case when same donor donates 2x max donation limit. one donor can donate no more than 30k to single receiver
        // @TODO add limit per transaction
        while ($remainingAmount > 0 && $i < count($donorsList)) {
            if ($donorCount[$i] <= MAX_DONATIONS && $donorsList[$i]["amount"] > 0) {
                $donation = min($donorsList[$i]["amount"], $remainingAmount);
                // ensure max donation amount per transaction
                $donation = min($donation, MAX_DONATION_AMOUNT);
                $donorsList[$i]["amount"] -= $donation;
                $remainingAmount -= $donation;
                $donorCount[$i]++;
                $result[] = [
                    "receiverName" => $receiver["receiverName"],
                    "accountNumber" => $receiver["accountNumber"],
                    "amount" => $donation,
                    "donorEmail" => $donorsList[$i]['donorEmail'],
                ];
            }
            $i++;
        }
        // @TODO test this
        if ($remainingAmount > 0) {
            $receiver['amount'] = $remainingAmount;
            $receiversIncomplete[] = $receiver;
        }
    }

    $donorsIncomplete = [];
    foreach ($donorsList as $donor) {
        if ($donor['amount'] > 0) {
            $donorsIncomplete[] = $donor;
        }
    }

    $trxListPath = compileAndSaveTrxList($result);
    $donorsLeftoverListPath = compileDonorsLeftover($donorsIncomplete);

    $archivePath = __DIR__ . "/../izlazne-liste.zip";
    $zip = new ZipArchive();
    if ($zip->open($archivePath, ZIPARCHIVE::CREATE) !== TRUE) {
        die ("Could not open archive");
    }
    $zip->addFile($trxListPath,"lista-uplata-po-donatorima.xlsx");
    $zip->addFile($donorsLeftoverListPath,"lista-preostalih-donatora.xlsx");
    $zip->close();
    $archiveContents = file_get_contents($archivePath);

    unlink($trxListPath);
    unlink($donorsLeftoverListPath);
    unlink($archivePath);
    unlink($donorsFilePath);
    unlink($receiversFilePath);

    //@TODO delete files
//header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment;filename="'.basename($archivePath).'"');
    header("Content-Transfer-Encoding: Binary");
    header('Cache-Control: max-age=0');
    echo $archiveContents;
}

function compileDonorsLeftover($donorsIncomplete) {
    $spreadsheet = new Spreadsheet();
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->getSpreadsheet()->getDefaultStyle()->getAlignment()->setWrapText(true);
    $sheet = $writer->getSpreadsheet()->getActiveSheet();

    $sheet->getCell('A1')->setValue('Donator email');
    $sheet->getCell('B1')->setValue('Iznos');

    foreach (['A', 'B', 'C'] as $letter) {
        $sheet->getColumnDimension($letter)->setAutoSize(true);
    }

    // prepare trx list
    foreach ($donorsIncomplete as $key => $donor) {
        $key = $key + 2; // @TODO check
        $sheet->getCell('A'.$key)->setValue($donor["donorEmail"]);
        $sheet->getCell('B'.$key)->setValue($donor["amount"]);
    }
    $filePath = __DIR__ . '/../lista-preostalih-donatora.xlsx';
    $writer->save($filePath);

    return $filePath;
}

function compileAndSaveTrxList($trxList) {
    $spreadsheet = new Spreadsheet();
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->getSpreadsheet()->getDefaultStyle()->getAlignment()->setWrapText(true);
    $sheet = $writer->getSpreadsheet()->getActiveSheet();

    $sheet->getCell('A1')->setValue('Donator email');
    $sheet->getCell('B1')->setValue('Primalac');
    $sheet->getCell('C1')->setValue('Iznos');
    $sheet->getCell('D1')->setValue('Br rachuna');

    foreach (['A', 'B', 'C', 'D', 'E'] as $letter) {
        $sheet->getColumnDimension($letter)->setAutoSize(true);
    }

    // prepare trx list
    foreach ($trxList as $key => $allocation) {
        $key = $key + 2; // @TODO check
        $sheet->getCell('A'.$key)->setValue($allocation["donorEmail"]);
        $sheet->getCell('B'.$key)->setValue($allocation["receiverName"]);
        $sheet->getCell('C'.$key)->setValue($allocation["amount"]);
        $sheet->getCell('D'.$key)->setValue($allocation["accountNumber"]);

//        echo $allocation["donorEmail"] . " donates " . $allocation["amount"] . " to " . $allocation["receiverName"] . "\n";
    }
    $filePath = __DIR__ . '/../lista-uplata-po-donatorima.xlsx';
    $writer->save($filePath);

    return $filePath;
}