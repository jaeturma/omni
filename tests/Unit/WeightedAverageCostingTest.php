<?php

use App\Support\WeightedAverageCosting;

beforeEach(fn () => $this->costing = new WeightedAverageCosting);

test('first receipt establishes quantity average and value', function () {
    expect($this->costing->inbound('0.0000', '0.0000', '5.0000', '125.5000'))->toBe([
        'quantity' => '5.0000',
        'average_cost' => '125.5000',
        'movement_value' => '627.5000',
    ]);
});

test('multiple receipts calculate a decimal-safe weighted average', function () {
    $first = $this->costing->inbound('0.0000', '0.0000', '10.0000', '100.0000');
    $second = $this->costing->inbound($first['quantity'], $first['average_cost'], '5.0000', '200.0000');

    expect($second)->toBe([
        'quantity' => '15.0000',
        'average_cost' => '133.3333',
        'movement_value' => '1000.0000',
    ]);
});

test('partial issues preserve the posting-time average and issue value', function () {
    $result = $this->costing->outbound('15.0000', '133.3333', '4.0000');

    expect($result)->toBe([
        'quantity' => '11.0000',
        'average_cost' => '133.3333',
        'issue_unit_cost' => '133.3333',
        'movement_value' => '533.3332',
    ]);
});

test('issuing the final quantity clears residual average cost', function () {
    $result = $this->costing->outbound('3.5000', '91.2500', '3.5000');

    expect($result['quantity'])->toBe('0.0000')
        ->and($result['average_cost'])->toBe('0.0000')
        ->and($result['issue_unit_cost'])->toBe('91.2500');
});

test('adjustments and transfers use the same inbound and outbound rules', function () {
    $adjusted = $this->costing->inbound('8.0000', '100.0000', '2.0000', '150.0000');
    $transferredOut = $this->costing->outbound($adjusted['quantity'], $adjusted['average_cost'], '4.0000');
    $transferredIn = $this->costing->inbound('5.0000', '80.0000', '4.0000', $transferredOut['issue_unit_cost']);

    expect($adjusted['average_cost'])->toBe('110.0000')
        ->and($transferredOut['average_cost'])->toBe('110.0000')
        ->and($transferredIn['quantity'])->toBe('9.0000')
        ->and($transferredIn['average_cost'])->toBe('93.3333');
});

test('reversals restore quantity and value consistently', function () {
    $received = $this->costing->inbound('10.0000', '100.0000', '5.0000', '200.0000');
    $receiptReversed = $this->costing->removeInbound($received['quantity'], $received['average_cost'], '5.0000', '200.0000');
    $issued = $this->costing->outbound('10.0000', '100.0000', '4.0000');
    $issueReversed = $this->costing->inbound($issued['quantity'], $issued['average_cost'], '4.0000', $issued['issue_unit_cost']);

    expect($receiptReversed['quantity'])->toBe('10.0000')
        ->and($receiptReversed['average_cost'])->toBe('99.9999')
        ->and($issueReversed['quantity'])->toBe('10.0000')
        ->and($issueReversed['average_cost'])->toBe('100.0000');
});

test('invalid quantities prevent division by zero and negative stock', function () {
    expect(fn () => $this->costing->inbound('0.0000', '0.0000', '0.0000', '100.0000'))
        ->toThrow(DomainException::class, 'Incoming quantity must be greater than zero.')
        ->and(fn () => $this->costing->outbound('1.0000', '100.0000', '2.0000'))
        ->toThrow(DomainException::class, 'Insufficient stock')
        ->and(fn () => $this->costing->removeInbound('2.0000', '10.0000', '1.0000', '25.0000'))
        ->toThrow(DomainException::class, 'negative inventory value');
});
