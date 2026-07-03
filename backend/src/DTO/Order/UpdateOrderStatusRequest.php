<?php

namespace App\DTO\Order;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateOrderStatusRequest
{
    #[Assert\NotBlank]
    #[Assert\Choice(
        choices: ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'],
        message: 'Status must be one of: pending, confirmed, shipped, delivered, cancelled'
    )]
    public string $status = '';
}
