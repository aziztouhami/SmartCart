<?php

namespace App\Service;

use App\DTO\Order\CheckoutRequest;
use App\Entity\Order;
use App\Entity\User;
use App\Repository\AddressRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;

class OrderService
{
    private const TRANSITIONS = [
        'pending'   => ['confirmed', 'cancelled'],
        'confirmed' => ['shipped', 'cancelled'],
        'shipped'   => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public function __construct(
        private OrderRepository $orderRepository,
        private AddressRepository $addressRepository,
        private EntityManagerInterface $em,
        private MailService $mailService,
        private InteractionService $interactionService,
    ) {}

    public function checkout(User $user, CheckoutRequest $dto): Order
    {
        $cart = $this->orderRepository->findCartByUser($user);
        if (!$cart || $cart->getOrderItems()->isEmpty()) {
            throw new \RuntimeException('Your cart is empty', 400);
        }

        if ($dto->addressId !== null) {
            $address = $this->addressRepository->find($dto->addressId);
            if (!$address || $address->getUser()->getId() !== $user->getId()) {
                throw new \RuntimeException('Address not found', 404);
            }
            $shippingAddress = json_encode([
                'label'        => $address->getLabel(),
                'street'       => $address->getStreet(),
                'city'         => $address->getCity(),
                'postalCode'   => $address->getPostalCode(),
                'country'      => $address->getCountry(),
                'contactPhone' => $dto->contactPhone,
            ]);
        } elseif ($dto->street && $dto->city && $dto->country) {
            $shippingAddress = json_encode([
                'street'       => $dto->street,
                'city'         => $dto->city,
                'postalCode'   => $dto->postalCode,
                'country'      => $dto->country,
                'contactPhone' => $dto->contactPhone,
            ]);
        } else {
            throw new \RuntimeException('Provide either addressId or street + city + country', 400);
        }

        $cart->setStatus('pending');
        $cart->setShippingAddress($shippingAddress);
        $cart->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        // Real purchase signal for the recommender — recorded server-side so it
        // can't be skipped or faked by a missing frontend call.
        foreach ($cart->getOrderItems() as $item) {
            $this->interactionService->track($user, $item->getProduct(), 'purchase', $item->getQuantity());
        }

        try {
            $this->mailService->sendOrderConfirmation($cart);
        } catch (\Throwable) {
            // Order placement must not fail because of a mail delivery issue.
        }

        return $cart;
    }

    /**
     * Customer-initiated cancellation. Unlike updateStatus(), this is only
     * ever reachable while the order is still 'pending' — once staff have
     * confirmed it, the customer can no longer self-cancel.
     */
    public function cancelOwnOrder(Order $order): Order
    {
        if ($order->getStatus() !== 'pending') {
            throw new \RuntimeException('Only pending orders can be cancelled', 400);
        }

        return $this->updateStatus($order, 'cancelled');
    }

    public function updateStatus(Order $order, string $newStatus): Order
    {
        $current = $order->getStatus();
        $allowed = self::TRANSITIONS[$current] ?? [];

        if (!in_array($newStatus, $allowed, true)) {
            throw new \RuntimeException(
                "Cannot move order from '{$current}' to '{$newStatus}'. Allowed: " . implode(', ', $allowed),
                400
            );
        }

        $order->setStatus($newStatus);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        try {
            if ($newStatus === 'shipped') {
                $this->mailService->sendOrderShipped($order);
            } elseif ($newStatus === 'delivered') {
                $this->mailService->sendOrderDelivered($order);
            }
        } catch (\Throwable) {
            // Status update must not fail because of a mail delivery issue.
        }

        return $order;
    }
}
