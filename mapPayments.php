<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;

include(__DIR__ . "/vendor/autoload.php");

const MAX_DONATIONS = 3;
const MAX_DONATION_AMOUNT = 30000;

$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$reader->setReadDataOnly(true);

$donorExcel = $reader->load(__DIR__ . '/Dummy donatori.xlsx');
$receiverExcel = $reader->load(__DIR__ . '/Dummy Prosvetari.xlsx');

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
$donorCount = array_fill(0, count($donorsList), 0);
foreach ($receiversList as $receiver) {
    $remainingAmount = $receiver["amount"];
    $i = 0;
    // @TODO add limit per transaction
    while ($remainingAmount > 0 && $i < count($donorsList)) {
        if ($donorCount[$i] <= MAX_DONATIONS && $donorsList[$i]["amount"] > 0) {
            $donation = min($donorsList[$i]["amount"], $remainingAmount);
            // ensure max donation amount
            $donation = min($donation, MAX_DONATION_AMOUNT);
            $donorsList[$i]["amount"] -= $donation;
            $remainingAmount -= $donation;
            $donorCount[$i]++;
            $result[] = [
//                "donorName" => $donorsList[$i]["donorName"],
                "receiverName" => $receiver["receiverName"],
                "accountNumber" => $receiver["accountNumber"],
                "amount" => $donation,
                "donorEmail" => $donorsList[$i]['donorEmail'],
            ];
        }
        $i++;
    }
}

$spreadsheet = new Spreadsheet();
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->getSpreadsheet()->getDefaultStyle()->getAlignment()->setWrapText(true);
$sheet = $writer->getSpreadsheet()->getActiveSheet();

//$sheet->getCell('A1')->setValue('Donator');
$sheet->getCell('A1')->setValue('Donator email');
$sheet->getCell('B1')->setValue('Primalac');
$sheet->getCell('C1')->setValue('Iznos');
$sheet->getCell('D1')->setValue('Br rachuna');

foreach (['A', 'B', 'C', 'D', 'E'] as $letter) {
    $sheet->getColumnDimension($letter)->setAutoSize(true);
}

//var_dump($result);
//die();

// Print the result
foreach ($result as $key => $allocation) {
    $key = $key + 2;
//    $sheet->getCell('A'.$key)->setValue($allocation["donorName"]);
    $sheet->getCell('A'.$key)->setValue($allocation["donorEmail"]);
    $sheet->getCell('B'.$key)->setValue($allocation["receiverName"]);
    $sheet->getCell('C'.$key)->setValue($allocation["amount"]);
    $sheet->getCell('D'.$key)->setValue($allocation["accountNumber"]);

    echo $allocation["donorEmail"] . " donates " . $allocation["amount"] . " to " . $allocation["receiverName"] . "\n";
}

$filePath = __DIR__ . '/trx-list.xlsx';
$writer->save($filePath);
