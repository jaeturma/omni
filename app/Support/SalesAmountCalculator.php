<?php

namespace App\Support;

use InvalidArgumentException;

final class SalesAmountCalculator
{
    private const MONEY_SCALE = 4;

    /** @return array{gross_amount: string, discount_amount: string, net_amount: string} */
    public function line(string $quantity, string $unitPrice, string $discountRate = '0'): array
    {
        $this->assertDecimal($quantity, 'quantity', 4);
        $this->assertDecimal($unitPrice, 'unit price', 4);
        $this->assertDecimal($discountRate, 'discount rate', 6);

        if (bccomp($discountRate, '100', 6) === 1) {
            throw new InvalidArgumentException('Discount rate cannot exceed 100 percent.');
        }

        $gross = $this->round(bcmul($quantity, $unitPrice, 8));
        $discount = $this->round(bcdiv(bcmul($gross, $discountRate, 10), '100', 10));

        return [
            'gross_amount' => $gross,
            'discount_amount' => $discount,
            'net_amount' => bcsub($gross, $discount, self::MONEY_SCALE),
        ];
    }

    /** @return array{gross_sales: string, discounts: string, net_sales: string, withholding: string, cash_received: string, balance_due: string} */
    public function settlement(string $grossSales, string $discounts, string $withholding, string $cashReceived): array
    {
        foreach (compact('grossSales', 'discounts', 'withholding', 'cashReceived') as $name => $amount) {
            $this->assertDecimal($amount, $name, self::MONEY_SCALE);
        }

        $netSales = bcsub($grossSales, $discounts, self::MONEY_SCALE);
        $balanceDue = bcsub(bcsub($netSales, $withholding, self::MONEY_SCALE), $cashReceived, self::MONEY_SCALE);

        if (bccomp($netSales, '0', self::MONEY_SCALE) === -1 || bccomp($balanceDue, '0', self::MONEY_SCALE) === -1) {
            throw new InvalidArgumentException('Discounts, withholding, and cash received cannot exceed the amount due.');
        }

        return [
            'gross_sales' => $this->round($grossSales),
            'discounts' => $this->round($discounts),
            'net_sales' => $netSales,
            'withholding' => $this->round($withholding),
            'cash_received' => $this->round($cashReceived),
            'balance_due' => $balanceDue,
        ];
    }

    /** @param list<array{gross_amount: string, discount_amount: string, net_amount: string}> $lines
     * @return array{subtotal: string, line_discount_total: string, document_discount_amount: string, grand_total: string}
     */
    public function document(array $lines, string $documentDiscountRate = '0'): array
    {
        $this->assertDecimal($documentDiscountRate, 'document discount rate', 6);
        if (bccomp($documentDiscountRate, '100', 6) === 1) {
            throw new InvalidArgumentException('Document discount rate cannot exceed 100 percent.');
        }

        $subtotal = $lineDiscounts = $netLines = '0.0000';
        foreach ($lines as $line) {
            $subtotal = bcadd($subtotal, $line['gross_amount'], self::MONEY_SCALE);
            $lineDiscounts = bcadd($lineDiscounts, $line['discount_amount'], self::MONEY_SCALE);
            $netLines = bcadd($netLines, $line['net_amount'], self::MONEY_SCALE);
        }
        $documentDiscount = $this->round(bcdiv(bcmul($netLines, $documentDiscountRate, 10), '100', 10));

        return ['subtotal' => $subtotal, 'line_discount_total' => $lineDiscounts,
            'document_discount_amount' => $documentDiscount,
            'grand_total' => bcsub($netLines, $documentDiscount, self::MONEY_SCALE)];
    }

    private function assertDecimal(string $value, string $field, int $scale): void
    {
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,'.$scale.'})?$/', $value)) {
            throw new InvalidArgumentException("The {$field} must be a non-negative decimal with at most {$scale} decimal places.");
        }
    }

    private function round(string $value): string
    {
        return bcadd($value, '0.00005', self::MONEY_SCALE);
    }
}
