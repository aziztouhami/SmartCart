<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\Promotion;
use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailService
{
    public function __construct(
        private MailerInterface $mailer,
        private OrderPdfService $orderPdfService,
        private string $frontendUrl,
        private string $adminEmail,
    ) {
    }

    /**
     * Runs a mail-send call, swallowing any failure. A mail delivery issue
     * (bad SMTP credentials, network blip, quota) must never fail the request
     * that triggered it — registration, checkout, an order status update, or
     * a promotion blast all still succeed even if the email doesn't go out.
     */
    public function sendSafely(callable $send): void
    {
        try {
            $send();
        } catch (\Throwable) {
            // Intentionally silent — see method docblock.
        }
    }

    public function sendVerificationEmail(User $user): void
    {
        $link = rtrim($this->frontendUrl, '/').'/verify-email?token='.$user->getVerificationToken();

        $email = (new Email())
            ->from($this->adminEmail)
            ->to($user->getEmail())
            ->subject('Confirm your SmartCart account')
            ->html($this->verificationHtml($user, $link));

        $this->mailer->send($email);
    }

    private function verificationHtml(User $user, string $link): string
    {
        $name = htmlspecialchars($user->getFirstName() ?? '', ENT_QUOTES);

        return <<<HTML
            <div style="font-family: Arial, sans-serif; max-width: 480px; margin: 0 auto; color: #1a2b40;">
                <h2 style="color: #185FA5;">Welcome to SmartCart{$this->namePart($name)}</h2>
                <p>Thanks for creating an account. Please confirm your email address to activate it.</p>
                <p style="margin: 28px 0;">
                    <a href="{$link}" style="background: #185FA5; color: #fff; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 600;">
                        Confirm My Account
                    </a>
                </p>
                <p style="font-size: 0.85rem; color: #64748b;">
                    If the button doesn't work, copy and paste this link into your browser:<br>
                    <a href="{$link}">{$link}</a>
                </p>
                <p style="font-size: 0.85rem; color: #64748b;">If you didn't create this account, you can safely ignore this email.</p>
            </div>
            HTML;
    }

    private function namePart(string $name): string
    {
        return '' !== $name ? ", {$name}" : '';
    }

    /**
     * Sent right after checkout — the order resume as a PDF attachment.
     */
    public function sendOrderConfirmation(Order $order): void
    {
        $user = $order->getUser();
        if (!$user) {
            return;
        }

        $pdf = $this->orderPdfService->render($order);

        $email = (new Email())
            ->from($this->adminEmail)
            ->to($user->getEmail())
            ->subject("Order #{$order->getId()} confirmed")
            ->html($this->orderStatusHtml(
                $order,
                'Order Received!',
                'Thanks for your order — we\'ve attached a PDF resume of your purchase. We\'ll let you know as soon as it ships.'
            ))
            ->attach($pdf, "order-{$order->getId()}.pdf", 'application/pdf');

        $this->mailer->send($email);
    }

    public function sendOrderShipped(Order $order): void
    {
        $user = $order->getUser();
        if (!$user) {
            return;
        }

        $email = (new Email())
            ->from($this->adminEmail)
            ->to($user->getEmail())
            ->subject("Order #{$order->getId()} has shipped")
            ->html($this->orderStatusHtml(
                $order,
                'Your Order Is On Its Way!',
                'Good news — your order has shipped and is heading to you now.'
            ));

        $this->mailer->send($email);
    }

    public function sendOrderDelivered(Order $order): void
    {
        $user = $order->getUser();
        if (!$user) {
            return;
        }

        $email = (new Email())
            ->from($this->adminEmail)
            ->to($user->getEmail())
            ->subject("Order #{$order->getId()} delivered — thank you!")
            ->html($this->orderStatusHtml(
                $order,
                'Delivered! Thank You for Shopping With Us',
                'Your order has been delivered. We hope you love it! Thank you for choosing SmartCart — we\'d love to see you again soon.',
                includeItems: true
            ));

        $this->mailer->send($email);
    }

    /**
     * Sent to every marketing-opted-in user when an admin creates a promotion.
     */
    public function sendPromotionEmail(User $user, Promotion $promotion): void
    {
        [$heading, $intro, $link] = $this->promotionContent($promotion);

        $email = (new Email())
            ->from($this->adminEmail)
            ->to($user->getEmail())
            ->subject($heading)
            ->html($this->promotionHtml($user, $heading, $intro, $link));

        $this->mailer->send($email);
    }

    private function promotionContent(Promotion $promotion): array
    {
        $discount = Promotion::DISCOUNT_FIXED === $promotion->getDiscountType()
            ? number_format((float) $promotion->getFixedPrice(), 3).' TND'
            : ((float) $promotion->getPercentage()).'% off';

        if (Promotion::TYPE_PRODUCT === $promotion->getType() && $promotion->getProduct()) {
            $name = $promotion->getProduct()->getName();

            return [
                "New promotion: {$name}",
                "{$name} is now on sale — {$discount}. Grab it before the offer ends.",
                rtrim($this->frontendUrl, '/').'/product/'.$promotion->getProduct()->getId(),
            ];
        }

        if (Promotion::TYPE_BRAND === $promotion->getType() && $promotion->getBrand()) {
            $name = $promotion->getBrand()->getName();

            return [
                "New promotion on {$name}",
                "All {$name} products are now {$discount}. Check out the deals.",
                rtrim($this->frontendUrl, '/').'/promotions',
            ];
        }

        return [
            'A new store-wide promotion just started!',
            "Enjoy {$discount} across the store for a limited time.",
            rtrim($this->frontendUrl, '/').'/promotions',
        ];
    }

    private function promotionHtml(User $user, string $heading, string $intro, string $link): string
    {
        $name = htmlspecialchars($user->getFirstName() ?? '', ENT_QUOTES);

        return <<<HTML
            <div style="font-family: Arial, sans-serif; max-width: 480px; margin: 0 auto; color: #1a2b40;">
                <h2 style="color: #185FA5;">{$heading}</h2>
                <p>Hi{$this->namePart($name)},</p>
                <p>{$intro}</p>
                <p style="margin: 28px 0;">
                    <a href="{$link}" style="background: #185FA5; color: #fff; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 600;">
                        Shop the Offer
                    </a>
                </p>
                <p style="font-size: 0.75rem; color: #94a3b8;">
                    You're receiving this because you opted in to promotional emails. You can turn this off anytime in your profile settings.
                </p>
            </div>
            HTML;
    }

    private function orderStatusHtml(Order $order, string $heading, string $intro, bool $includeItems = false): string
    {
        $itemsHtml = '';
        if ($includeItems) {
            $rows = '';
            foreach ($order->getOrderItems() as $item) {
                $name = htmlspecialchars($item->getProduct()?->getName() ?? 'Product', ENT_QUOTES);
                $qty = $item->getQuantity();
                $subtotal = number_format((float) $item->getPrice() * $qty, 3);
                $rows .= <<<HTML
                    <tr>
                        <td style="padding:6px 0; border-bottom:1px solid #e2e8f0;">{$name} &times;{$qty}</td>
                        <td style="padding:6px 0; border-bottom:1px solid #e2e8f0; text-align:right;">{$subtotal} TND</td>
                    </tr>
                    HTML;
            }
            $total = number_format((float) $order->getTotalAmount(), 3);
            $itemsHtml = <<<HTML
                <table style="width: 100%; margin: 20px 0; font-size: 0.9rem;">
                    {$rows}
                    <tr>
                        <td style="padding-top:10px;"><strong>Total</strong></td>
                        <td style="padding-top:10px; text-align:right;"><strong>{$total} TND</strong></td>
                    </tr>
                </table>
                HTML;
        }

        $ordersLink = rtrim($this->frontendUrl, '/').'/orders';

        return <<<HTML
            <div style="font-family: Arial, sans-serif; max-width: 480px; margin: 0 auto; color: #1a2b40;">
                <h2 style="color: #185FA5;">{$heading}</h2>
                <p>{$intro}</p>
                <p style="color: #64748b; font-size: 0.9rem;">Order #{$order->getId()}</p>
                {$itemsHtml}
                <p style="margin: 24px 0;">
                    <a href="{$ordersLink}" style="background: #185FA5; color: #fff; padding: 10px 24px; border-radius: 8px; text-decoration: none; font-weight: 600;">
                        View My Orders
                    </a>
                </p>
            </div>
            HTML;
    }
}
