<?php

namespace App\Service;

use App\Entity\Order;
use Dompdf\Dompdf;
use Dompdf\Options;

class OrderPdfService
{
    /**
     * Render an order resume (summary) as a PDF and return the raw bytes.
     */
    public function render(Order $order): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->html($order));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function html(Order $order): string
    {
        $address = $order->getShippingAddress() ? (json_decode($order->getShippingAddress(), true) ?? []) : [];
        $user = $order->getUser();

        $rows = '';
        foreach ($order->getOrderItems() as $item) {
            $name = htmlspecialchars($item->getProduct()?->getName() ?? 'Product', ENT_QUOTES);
            $qty = $item->getQuantity();
            $unit = number_format((float) $item->getPrice(), 3);
            $subtotal = number_format((float) $item->getPrice() * $qty, 3);
            $rows .= <<<HTML
                <tr>
                    <td style="padding:8px 0; border-bottom:1px solid #e2e8f0;">{$name}</td>
                    <td style="padding:8px 0; border-bottom:1px solid #e2e8f0; text-align:center;">{$qty}</td>
                    <td style="padding:8px 0; border-bottom:1px solid #e2e8f0; text-align:right;">{$unit} TND</td>
                    <td style="padding:8px 0; border-bottom:1px solid #e2e8f0; text-align:right;">{$subtotal} TND</td>
                </tr>
                HTML;
        }

        $addressLine = htmlspecialchars(implode(', ', array_filter([
            $address['street'] ?? null,
            $address['city'] ?? null,
            $address['postalCode'] ?? null,
            $address['country'] ?? null,
        ])), ENT_QUOTES);

        $customerName = htmlspecialchars(trim(($user?->getFirstName() ?? '') . ' ' . ($user?->getLastName() ?? '')), ENT_QUOTES);
        $total = number_format((float) $order->getTotalAmount(), 3);
        $date = $order->getCreatedAt()->format('d M Y, H:i');

        return <<<HTML
            <html>
            <head><meta charset="utf-8"></head>
            <body style="font-family: Helvetica, Arial, sans-serif; color: #1a2b40; padding: 20px;">
                <h1 style="color: #185FA5; font-size: 22px; margin-bottom: 0;">SmartCart</h1>
                <p style="color: #64748b; margin-top: 4px;">Order Confirmation</p>

                <table style="width: 100%; margin: 24px 0;">
                    <tr>
                        <td>
                            <strong>Order #{$order->getId()}</strong><br>
                            Date: {$date}
                        </td>
                        <td style="text-align: right;">
                            <strong>{$customerName}</strong><br>
                            {$user?->getEmail()}
                        </td>
                    </tr>
                </table>

                <p><strong>Shipping address:</strong> {$addressLine}</p>

                <table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
                    <thead>
                        <tr style="border-bottom: 2px solid #185FA5;">
                            <th style="text-align:left; padding-bottom:8px;">Product</th>
                            <th style="text-align:center; padding-bottom:8px;">Qty</th>
                            <th style="text-align:right; padding-bottom:8px;">Unit Price</th>
                            <th style="text-align:right; padding-bottom:8px;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$rows}
                    </tbody>
                </table>

                <table style="width: 100%; margin-top: 16px;">
                    <tr>
                        <td></td>
                        <td style="text-align: right; font-size: 16px;">
                            <strong>Total: {$total} TND</strong>
                        </td>
                    </tr>
                </table>

                <p style="margin-top: 32px; color: #64748b; font-size: 12px;">
                    Thank you for shopping with SmartCart.
                </p>
            </body>
            </html>
            HTML;
    }
}
