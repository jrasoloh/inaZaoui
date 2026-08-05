<?php

namespace App\Tests\Form;

use App\Entity\User;
use App\Form\GuestType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class GuestTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        // The form uses validation constraints (plainPassword) so the
        // ValidatorExtension must be registered on the test factory.
        $validator = Validation::createValidator();

        return [
            new ValidatorExtension($validator),
        ];
    }

    public function testSubmitValidDataMapsToUser(): void
    {
        $formData = [
            'name' => 'New Guest',
            'email' => 'new.guest@example.com',
            'description' => 'A brand new guest',
            'plainPassword' => 'secret123',
        ];

        $model = new User();
        $form = $this->factory->create(GuestType::class, $model);

        $form->submit($formData);

        self::assertTrue($form->isSynchronized());
        self::assertSame('New Guest', $model->getName());
        self::assertSame('new.guest@example.com', $model->getEmail());
        self::assertSame('A brand new guest', $model->getDescription());
        // plainPassword is unmapped: it must not populate the entity password.
        self::assertNull($model->getPassword());
        self::assertSame('secret123', $form->get('plainPassword')->getData());
    }

    public function testFormExposesExpectedFields(): void
    {
        $form = $this->factory->create(GuestType::class, new User());

        self::assertTrue($form->has('name'));
        self::assertTrue($form->has('email'));
        self::assertTrue($form->has('description'));
        self::assertTrue($form->has('plainPassword'));
    }
}


