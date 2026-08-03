<?php

namespace App\Domain\Http;

use App\Domain\Exception\ApiException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Deserializes + validates a request body into a DTO, replacing the
 * try/catch-deserialize + validate-and-400 block that used to be repeated
 * in nearly every write endpoint across the controller layer.
 */
class RequestDtoParser
{
    public function __construct(
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $dtoClass
     *
     * @return T
     */
    public function parse(Request $request, string $dtoClass): object
    {
        try {
            $dto = $this->serializer->deserialize($request->getContent(), $dtoClass, 'json');
        } catch (\Throwable) {
            throw new ApiException('Invalid JSON body', 400);
        }

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            throw new ApiException((string) $errors, 400);
        }

        return $dto;
    }
}
