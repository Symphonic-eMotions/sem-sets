<?php
declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $hasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        $users = [
            [
                'email' => 'admin@example.com',
                'pin'   => '123456',
                'roles' => ['ROLE_ADMIN'],
            ],
            [
                'email' => 'editor@example.com',
                'pin'   => '654321',
                'roles' => ['ROLE_EDITOR'],
            ],
            [
                'email' => 'readonly@example.com',
                'pin'   => '111111',
                'roles' => ['ROLE_READONLY'],
            ],
            [
                'email' => 'tester@example.com',
                'pin'   => '222222',
                'roles' => ['ROLE_EDITOR'],
            ],
            [
                'email' => 'audit@example.com',
                'pin'   => '333333',
                'roles' => ['ROLE_READONLY'],
            ],
        ];

        foreach ($users as $info) {
            $user = new User();
            $user->setEmail($info['email']);
            $user->setPassword($this->hasher->hashPassword($user, $info['pin']));
            $user->setRoles($info['roles']);
            $user->setIsActive(true);

            $manager->persist($user);
        }

        $manager->flush();
    }
}
