<?php
// src/Command/CreateUserCommand.php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Maak een nieuwe gebruiker aan met email en 6-cijferige PIN'
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        $io->title('Nieuwe gebruiker aanmaken');

        // Email
        $emailQuestion = new Question('Email adres: ');
        $emailQuestion->setValidator(function ($answer) {
            if (empty($answer) || !filter_var($answer, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Voer een geldig email adres in');
            }
            return $answer;
        });
        $email = $helper->ask($input, $output, $emailQuestion);

        // Check of email al bestaat
        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            $io->error("Gebruiker met email {$email} bestaat al!");
            return Command::FAILURE;
        }

        // PIN
        $pinQuestion = new Question('6-cijferige PIN: ');
        $pinQuestion->setHidden(true);
        $pinQuestion->setValidator(function ($answer) {
            if (!preg_match('/^\d{6}$/', $answer)) {
                throw new \RuntimeException('PIN moet exact 6 cijfers zijn');
            }
            return $answer;
        });
        $pin = $helper->ask($input, $output, $pinQuestion);

        // Bevestig PIN
        $confirmQuestion = new Question('Bevestig PIN: ');
        $confirmQuestion->setHidden(true);
        $confirmPin = $helper->ask($input, $output, $confirmQuestion);

        if ($pin !== $confirmPin) {
            $io->error('PINs komen niet overeen!');
            return Command::FAILURE;
        }

        // Role
        $roleQuestion = new ChoiceQuestion(
            'Selecteer rol',
            ['ADMIN' => 'Administrator', 'EDITOR' => 'Editor', 'READONLY' => 'Read-only'],
            'EDITOR'
        );
        $roleChoice = $helper->ask($input, $output, $roleQuestion);

        $roleMap = [
            'Administrator' => 'ROLE_ADMIN',
            'Editor' => 'ROLE_EDITOR',
            'Read-only' => 'ROLE_READONLY'
        ];
        $role = $roleMap[$roleChoice];

        // Maak gebruiker
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->hasher->hashPassword($user, $pin));
        $user->setRoles([$role]);
        $user->setIsActive(true);

        $this->em->persist($user);
        $this->em->flush();

        $io->success([
            'Gebruiker succesvol aangemaakt!',
            "Email: {$email}",
            "Rol: {$role}"
        ]);

        return Command::SUCCESS;
    }
}