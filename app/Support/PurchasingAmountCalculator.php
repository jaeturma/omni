<?php

namespace App\Support;

use InvalidArgumentException;

final class PurchasingAmountCalculator
{
    private const MONEY_SCALE = 4;

    /** @return array{gross_amount: string, discount_amount: string, net_amount: string} */
    public function line(string $quantity, string $unitCost, string $discountRate = '0'): array
    {
        $this->assertDecimal($quantity, 'quantity', 4);
        $this->assertDecimal($unitCost, 'unit cost', 4);
        $this->assertRate($discountRate, 'discount rate');

        $gross = $this->round(bcmul($quantity, $unitCost, 8));
        $discount = $this->percentage($gross, $discountRate);

        return ['gross_amount' => $gross, 'discount_amount' => $discount,
            'net_amount' => bcsub($gross, $discount, self::MONEY_SCALE)];
    }

    /** @return array{gross_purchase: string, discounts: string, net_purchase: string, freight: string, total_due: string, withholding: string, cash_paid: string, balance_due: string} */
    public function settlement(string $grossPurchase, string $discounts, string $freight, string $withholding, string $cashPaid): array
    {
        foreach (compact('grossPurchase', 'discounts', 'freight', 'withholding', 'cashPaid') as $name => $amount) {
            $this->assertDecimal($amount, $name, self::MONEY_SCALE);
        }

        $netPurchase = bcsub($grossPurchase, $discounts, self::MONEY_SCALE);
        $totalDue = bcadd($netPurchase, $freight, self::MONEY_SCALE);
        $balanceDue = bcsub(bcsub($totalDue, $withholding, self::MONEY_SCALE), $cashPaid, self::MONEY_SCALE);

        if (bccomp($netPurchase, '0', self::MONEY_SCALE) === -1 || bccomp($balanceDue, '0', self::MONEY_SCALE) === -1) {
            throw new InvalidArgumentException('Discounts, withholding, and cash paid cannot exceed the applicable amount due.');
        }

        return [
            'gross_purchase' => $this->round($grossPurchase), 'discounts' => $this->round($discounts),
            'net_purchase' => $netPurchase, 'freight' => $this->round($freight), 'total_due' => $totalDue,
            'withholding' => $this->round($withholding), 'cash_paid' => $this->round($cashPaid), 'balance_due' => $balanceDue,
        ];
    }

    /**
     * @param  list<array{gross_amount: string, discount_amount: string, net_amount: string}>  $lines
     * @return array{subtotal: string, line_discount_total: string, document_discount_amount: string, freight: string, grand_total: string}
     */
    public function document(array $lines, string $documentDiscountRate = '0', string $freight = '0'): array
    {
        $this->assertRate($documentDiscountRate, 'document discount rate');
        $this->assertDecimal($freight, 'freight', self::MONEY_SCALE);

        $subtotal = $lineDiscounts = $netLines = '0.0000';
        foreach ($lines as $line) {
            $subtotal = bcadd($subtotal, $line['gross_amount'], self::MONEY_SCALE);
            $lineDiscounts = bcadd($lineDiscounts, $line['discount_amount'], self::MONEY_SCALE);
            $netLines = bcadd($netLines, $line['net_amount'], self::MONEY_SCALE);
        }
        $documentDiscount = $this->percentage($netLines, $documentDiscountRate);

        return [
            'subtotal' => $subtotal, 'line_discount_total' => $lineDiscounts,
            'document_discount_amount' => $documentDiscount, 'freight' => $this->round($freight),
            'grand_total' => bcadd(bcsub($netLines, $documentDiscount, self::MONEY_SCALE), $freight, self::MONEY_SCALE),
        ];
    }

    private function assertRate(string $value, string $field): void
    {
        $this->assertDecimal($value, $field, 6);
        if (bccomp($value, '100', 6) === 1) {
            throw new InvalidArgumentException(ucfirst($field).' cannot exceed 100 percent.');
        }
    }

    private function assertDecimal(string $value, string $field, int $scale): void
    {
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,'.$scale.'})?$/', $value)) {
            throw new InvalidArgumentException("The {$field} must be a non-negative decimal with at most {$scale} decimal places.");
        }
    }

    private function percentage(string $amount, string $rate): string
    {
        return $this->round(bcdiv(bcmul($amount, $rate, 10), '100', 10));
    }

    private function round(string $value): string
    {
        return bcadd($value, '0.00005', self::MONEY_SCALE);
    }
}
