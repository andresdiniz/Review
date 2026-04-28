<?php
// src/Controller/Admin/UserCrudController.php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractCrudController
{
    private const AVAILABLE_ROLES = [
        'Usuário'        => 'ROLE_USER',
        'Administrador'  => 'ROLE_ADMIN',
        'Editor'         => 'ROLE_EDITOR',
    ];

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private AdminUrlGenerator $adminUrlGenerator
    ) {}

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Usuário')
            ->setEntityLabelInPlural('Usuários')
            ->setPageTitle('index', 'Gerenciar Usuários')
            ->setPageTitle('new', 'Novo Usuário')
            ->setPageTitle('edit', 'Editar Usuário')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['email', 'firstName', 'lastName']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield EmailField::new('email', 'E-mail');

        yield TextField::new('firstName', 'Nome')
            ->hideOnIndex();

        yield TextField::new('lastName', 'Sobrenome')
            ->hideOnIndex();

        // Campo de senha: exibido apenas no formulário de criação e edição
        if (in_array($pageName, [Crud::PAGE_NEW, Crud::PAGE_EDIT], true)) {
            yield TextField::new('plainPassword', 'Senha')
                ->setFormType(RepeatedType::class)
                ->setFormTypeOptions([
                    'type'            => PasswordType::class,
                    'first_options'   => ['label' => 'Nova Senha', 'attr' => ['autocomplete' => 'new-password']],
                    'second_options'  => ['label' => 'Confirmar Senha'],
                    'mapped'          => false,
                    'required'        => $pageName === Crud::PAGE_NEW,
                    'invalid_message' => 'As senhas não conferem.',
                ])
                ->setRequired($pageName === Crud::PAGE_NEW)
                ->setHelp($pageName === Crud::PAGE_EDIT ? 'Deixe em branco para manter a senha atual.' : '');
        }

        yield ChoiceField::new('roles', 'Perfis')
            ->setChoices(self::AVAILABLE_ROLES)
            ->allowMultipleChoices()
            ->renderAsBadges([
                'ROLE_ADMIN'  => 'danger',
                'ROLE_EDITOR' => 'warning',
                'ROLE_USER'   => 'success',
            ]);

        yield DateTimeField::new('createdAt', 'Criado em')
            ->hideOnForm()
            ->setFormat('dd/MM/yyyy HH:mm');

        yield DateTimeField::new('updatedAt', 'Atualizado em')
            ->hideOnForm()
            ->setFormat('dd/MM/yyyy HH:mm');
    }

    public function configureActions(Actions $actions): Actions
    {
        $resetPassword = Action::new('resetPassword', 'Redefinir Senha', 'fa fa-key')
            ->linkToCrudAction('resetPassword')
            ->setCssClass('btn btn-sm btn-warning');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $resetPassword)
            ->add(Crud::PAGE_EDIT, $resetPassword);
    }

    /**
     * Intercepta persistência para hash da senha em novos usuários.
     */
    public function persistEntity(\Doctrine\ORM\EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->hashPasswordIfProvided($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    /**
     * Intercepta atualização para hash da senha se alterada.
     */
    public function updateEntity(\Doctrine\ORM\EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->hashPasswordIfProvided($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    /**
     * Ação para redefinir a senha de um usuário via formulário.
     */
    public function resetPassword(AdminContext $context, Request $request): Response
    {
        /** @var User $user */
        $user = $context->getEntity()->getInstance();

        $form = $this->createFormBuilder()
            ->add('password', RepeatedType::class, [
                'type'            => PasswordType::class,
                'first_options'   => ['label' => 'Nova Senha'],
                'second_options'  => ['label' => 'Confirmar Nova Senha'],
                'invalid_message' => 'As senhas não conferem.',
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('password')->getData();
            $hashed      = $this->passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashed);

            $em = $this->container->get('doctrine')->getManager();
            $em->flush();

            $this->addFlash('success', sprintf('Senha do usuário "%s" redefinida com sucesso.', $user->getEmail()));

            return $this->redirect(
                $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::INDEX)
                    ->generateUrl()
            );
        }

        return $this->render('admin/reset_password.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    private function hashPasswordIfProvided(User $user): void
    {
        // O campo plainPassword é 'mapped' => false; pegamos via getter se existir
        // Na prática, o EasyAdmin não mapeia automaticamente — verificamos se a senha foi definida
        // através do formulário com mapped=false e pegamos via request ou campo virtual
        // Esta lógica funciona se o campo plainPassword for adicionado como virtual na entidade
        if (method_exists($user, 'getPlainPassword') && $user->getPlainPassword()) {
            $hashed = $this->passwordHasher->hashPassword($user, $user->getPlainPassword());
            $user->setPassword($hashed);
            $user->eraseCredentials();
        }
    }
}
