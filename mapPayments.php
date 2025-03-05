<?php

const MAX_DONATIONS = 3;

$donors = [
    ["name" => "Donor1", "amount" => 50000],
    ["name" => "Donor2", "amount" => 15000],
    ["name" => "Donor3", "amount" => 5000],
    ["name" => "Donor3", "amount" => 5000],
    ["name" => "Donor5", "amount" => 1500],

];

$receivers = [
    ["name" => "Receiver1", "amount" => 33287],
    ["name" => "Receiver2", "amount" => 15125.45],
    ["name" => "Receiver2", "amount" => 12147.24],
    ["name" => "Receiver3", "amount" => 10412.78],
];

// Sort in descending order based on amounts
usort($donors, function ($a, $b) {
    return $b["amount"] <=> $a["amount"];
});
usort($receivers, function ($a, $b) {
    return $b["amount"] <=> $a["amount"];
});

$result = [];
$donorCount = array_fill(0, count($donors), 0);
foreach ($receivers as $receiver) {
    $remainingAmount = $receiver["amount"];
    $i = 0;
    // @TODO add limit per transaction
    while ($remainingAmount > 0 && $i < count($donors)) {
        if ($donorCount[$i] <= MAX_DONATIONS && $donors[$i]["amount"] > 0) {
            $donation = min($donors[$i]["amount"], $remainingAmount);
            $donors[$i]["amount"] -= $donation;
            $remainingAmount -= $donation;
            $donorCount[$i]++;
            $result[] = [
                "donor" => $donors[$i]["name"],
                "receiver" => $receiver["name"],
                "amount" => $donation
            ];
        }
        $i++;
    }
}

// Print the result
foreach ($result as $allocation) {
    echo $allocation["donor"] . " donates " . $allocation["amount"] . " to " . $allocation["receiver"] . "\n";
}