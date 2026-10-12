<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Form\UserAdminType;
use App\Entity\User;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * Comptes de l'administration (super administrateur uniquement) : création avec invitation par e-mail (le mot de passe
 * se choisit via le lien), rôles, activation, réinitialisation de la double authentification.
 */
final class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
    ) {
    }

    #[Route('/admin/utilisateurs/nouveau', name: 'app_admin_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        UserPasswordHasherInterface $hasher,
        MailerInterface $mailer,
        TranslatorInterface $translator,
        #[Autowire('%env(MAILER_FROM)%')]
        string $from,
    ): Response {
        $this->denyAccessUnlessGranted(Permission::USER_MANAGE);

        $user = new User();
        $form = $this->createForm(UserAdminType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Mot de passe aléatoire inutilisable : l'utilisateur en choisit un via le lien d'invitation.
            $user->setPassword($hasher->hashPassword($user, bin2hex(random_bytes(32))));
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            $this->invite($user, $mailer, $translator, $from);
            $this->addFlash('success', 'admin.flash.user_created');

            return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/user/form.html.twig', ['form' => $form, 'user' => null, 'self' => false], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/admin/utilisateurs/{id}', name: 'app_admin_user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted(Permission::USER_MANAGE);
        $user = $this->entityManager->find(User::class, $id) ?? throw $this->createNotFoundException();
        $self = $this->getUser() instanceof User && $this->getUser()->getId() === $user->getId();

        $form = $this->createForm(UserAdminType::class, $user, ['self' => $self]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'admin.flash.saved');

            return $this->redirectToRoute('app_admin_user_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/user/form.html.twig', ['form' => $form, 'user' => $user, 'self' => $self], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * Supprime le secret TOTP : l'utilisateur devra enrôler à nouveau son application à sa prochaine connexion.
     * À réserver aux pertes de téléphone, après vérification d'identité (la fenêtre d'enrôlement est celle d'une première connexion).
     */
    #[Route('/admin/utilisateurs/{id}/reinitialiser-2fa', name: 'app_admin_user_reset_2fa', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function resetTwoFactor(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted(Permission::USER_MANAGE);
        $user = $this->entityManager->find(User::class, $id) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('admin_user_2fa_'.$id, $request->request->getString('_token')) || $this->getUser() === $user) {
            throw $this->createAccessDeniedException();
        }

        $user->setTotpSecret(null);
        $this->entityManager->flush();
        $this->addFlash('success', 'admin.flash.2fa_reset');

        return $this->redirectToRoute('app_admin_user_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }

    private function invite(User $user, MailerInterface $mailer, TranslatorInterface $translator, string $from): void
    {
        try {
            $token = $this->resetPasswordHelper->generateResetToken($user);
            $mailer->send((new TemplatedEmail())
                ->from(new Address($from, $translator->trans('admin.site')))
                ->to((string) $user->getEmail())
                ->subject($translator->trans('admin.invite.subject'))
                ->htmlTemplate('admin/email/reset_password.html.twig')
                ->textTemplate('admin/email/reset_password.txt.twig')
                ->context(['resetToken' => $token, 'user' => $user]));
        } catch (ResetPasswordExceptionInterface|TransportExceptionInterface) {
            $this->addFlash('warning', 'admin.flash.invite_failed');
        }
    }
}
