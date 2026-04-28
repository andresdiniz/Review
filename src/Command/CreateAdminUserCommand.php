<?php
// src/Command/CreateAdminUserCommand.php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validation;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Cria um novo usuário administrador de forma interativa.',
    aliases: ['app:create-admin-user']
)]
class CreateAdminUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'E-mail do novo usuário administrador.')
            ->addOption('role', 'r', InputOption::VALUE_OPTIONAL,
                'Perfil do usuário: ROLE_ADMIN ou ROLE_EDITOR.', 'ROLE_ADMIN')
            ->addOption('first-name', null, InputOption::VALUE_OPTIONAL, 'Primeiro nome.')
            ->addOption('last-name', null, InputOption::VALUE_OPTIONAL, 'Sobrenome.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        $io->title('Criação de Usuário Administrador');

        // --- E-mail ---
        $email = $input->getArgument('email');
        if (!$email) {
            $question = new Question('E-mail: ');
            $question->setValidator(function ($value) {
                return $this->validateEmail($value);
            });
            $email = $helper->ask($input, $output, $question);
        } else {
            $this->validateEmail($email); // valida mesmo quando passado por argumento
        }

        // Verifica duplicidade
        if ($this->userRepository->findOneBy(['email' => $email])) {
            $io->error("Já existe um usuário com o e-mail \"{$email}\".");
            return Command::FAILURE;
        }

        // --- Senha ---
        $passwordQuestion = new Question('Senha (mínimo 8 caracteres): ');
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setHiddenFallback(false);
        $passwordQuestion->setValidator(function ($value) {
            if (strlen((string) $value) < 8) {
                throw new \RuntimeException('A senha deve ter pelo menos 8 caracteres.');
            }
            return $value;
        });
        $plainPassword = $helper->ask($input, $output, $passwordQuestion);

        // Confirmação de senha
        $confirmQuestion = new Question('Confirme a senha: ');
        $confirmQuestion->setHidden(true);
        $confirmQuestion->setHiddenFallback(false);
        $confirm = $helper->ask($input, $output, $confirmQuestion);

        if ($plainPassword !== $confirm) {
            $io->error('As senhas não conferem.');
            return Command::FAILURE;
        }

        // --- Nome ---
        $firstName = $input->getOption('first-name');
        if (!$firstName) {
            $question  = new Question('Primeiro nome (opcional, Enter para pular): ');
            $firstName = $helper->ask($input, $output, $question);
        }

        $lastName = $input->getOption('last-name');
        if (!$lastName) {
            $question = new Question('Sobrenome (opcional, Enter para pular): ');
            $lastName = $helper->ask($input, $output, $question);
        }

        // --- Perfil ---
        $role = strtoupper((string) $input->getOption('role'));
        if (!in_array($role, ['ROLE_ADMIN', 'ROLE_EDITOR', 'ROLE_USER'], true)) {
            $roleQuestion = new ChoiceQuestion(
                'Selecione o perfil do usuário:',
                ['ROLE_ADMIN' => 'Administrador', 'ROLE_EDITOR' => 'Editor', 'ROLE_USER' => 'Usuário'],
                'ROLE_ADMIN'
            );
            $role = $helper->ask($input, $output, $roleQuestion);
        }

        // --- Resumo e confirmação ---
        $io->section('Resumo');
        $io->table(['Campo', 'Valor'], [
            ['E-mail',    $email],
            ['Nome',      trim(($firstName ?? '') . ' ' . ($lastName ?? '')) ?: '(não informado)'],
            ['Perfil',    $role],
        ]);

        if (!$io->confirm('Criar este usuário?', true)) {
            $io->warning('Operação cancelada pelo usuário.');
            return Command::SUCCESS;
        }

        // --- Criação ---
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setRoles([$role]);

        if ($firstName) {
            $user->setFirstName($firstName);
        }
        if ($lastName) {
            $user->setLastName($lastName);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf(
            'Usuário administrador "%s" criado com sucesso! (ID: %d)',
            $email,
            $user->getId()
        ));

        return Command::SUCCESS;
    }

    /**
     * Valida o formato do e-mail e retorna o valor se válido.
     *
     * @throws \RuntimeException se o e-mail for inválido
     */
    private function validateEmail(?string $value): string
    {
        if (empty($value)) {
            throw new \RuntimeException('O e-mail não pode ser vazio.');
        }

        $validator   = Validation::createValidator();
        $violations  = $validator->validate($value, [new Email()]);

        if (count($violations) > 0) {
            throw new \RuntimeException("E-mail inválido: \"{$value}\".");
        }

        return strtolower(trim($value));
    }
}
