<?php

namespace App\Tests\Unit\Domain\Http;

use App\Domain\Exception\ApiException;
use App\Domain\Http\RequestDtoParser;
use App\DTO\Auth\LoginRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RequestDtoParserTest extends TestCase
{
    private SerializerInterface $serializer;
    private ValidatorInterface $validator;
    private RequestDtoParser $parser;

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->parser = new RequestDtoParser($this->serializer, $this->validator);
    }

    public function testReturnsDtoWhenValid(): void
    {
        $dto = new LoginRequest();
        $dto->email = 'user@example.com';
        $dto->password = 'secret123';

        $this->serializer->method('deserialize')->willReturn($dto);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $request = Request::create('/api/auth/login', 'POST', content: '{"email":"user@example.com","password":"secret123"}');

        $result = $this->parser->parse($request, LoginRequest::class);

        $this->assertSame($dto, $result);
    }

    public function testThrowsApiExceptionOnMalformedJson(): void
    {
        $this->serializer->method('deserialize')->willThrowException(new \UnexpectedValueException('bad json'));

        $request = Request::create('/api/auth/login', 'POST', content: 'not json');

        try {
            $this->parser->parse($request, LoginRequest::class);
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame('Invalid JSON body', $e->getMessage());
            $this->assertSame(400, $e->getCode());
            $this->assertNull($e->getApiCode());
        }
    }

    public function testThrowsApiExceptionOnValidationFailure(): void
    {
        $dto = new LoginRequest();
        $this->serializer->method('deserialize')->willReturn($dto);

        $violation = new ConstraintViolation('Email is required', null, [], $dto, 'email', '');
        $this->validator->method('validate')->willReturn(new ConstraintViolationList([$violation]));

        $request = Request::create('/api/auth/login', 'POST', content: '{}');

        try {
            $this->parser->parse($request, LoginRequest::class);
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(400, $e->getCode());
            $this->assertStringContainsString('Email is required', $e->getMessage());
        }
    }
}
