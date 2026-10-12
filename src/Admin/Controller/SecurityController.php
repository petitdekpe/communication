<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Form\ForgotPasswordType;
use App\Admin\Form\NewPasswordType;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
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
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * Connexion, mot de passe oublié (réinitialisation par e-mail) et enrôlement de la double authentification.
 * Le code TOTP lui-même est demandé par scheb/2fa-bundle (routes /admin/2fa et /admin/2fa_check).
 */
final class SecurityController extends AbstractController
{
    use ResetPasswordControllerTrait;

    private const string SETUP_SECRET = 'admin_totp_setup_secret';

    public function __construct(private readonly ResetPasswordHelperInterface $resetPasswordHelper)
    {
    }

    #[Route('/admin/login', name: 'app_admin_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        return $this->render('admin/security/login.html.twig', [
            'last_email' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    /** Intercepté par le pare-feu : cette méthode n'est jamais exécutée. */
    #[Route('/admin/logout', name: 'app_admin_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Déconnexion gérée par le pare-feu.');
    }

    #[Route('/admin/mot-de-passe-oublie', name: 'app_admin_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        UserRepository $users,
        MailerInterface $mailer,
        #[Autowire('%env(MAILER_FROM)%')]
        string $from,
        TranslatorInterface $translator,
    ): Response {
        $form = $this->createForm(ForgotPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $users->findByEmail((string) $form->get('email')->getData());
            // Même réponse que le compte existe ou non (ou soit désactivé, ou limité par le bundle) : rien n'est révélé.
            if ($user instanceof User && $user->isActive()) {
                try {
                    $token = $this->resetPasswordHelper->generateResetToken($user);
                    $mailer->send((new TemplatedEmail())
                        ->from(new Address($from, $translator->trans('admin.site')))
                        ->to((string) $user->getEmail())
                        ->subject($translator->trans('admin.reset.mail.subject'))
                        ->htmlTemplate('admin/email/reset_password.html.twig')
                        ->textTemplate('admin/email/reset_password.txt.twig')
                        ->context(['resetToken' => $token, 'user' => $user]));
                } catch (ResetPasswordExceptionInterface|TransportExceptionInterface) {
                    // Délai entre deux demandes ou transport indisponible : silence, même page de confirmation.
                }
            }

            return $this->redirectToRoute('app_admin_check_email');
        }

        return $this->render('admin/security/forgot_password.html.twig', ['form' => $form], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/admin/mot-de-passe-oublie/verification', name: 'app_admin_check_email', methods: ['GET'])]
    public function checkEmail(): Response
    {
        return $this->render('admin/security/check_email.html.twig', ['lifetime' => $this->resetPasswordHelper->getTokenLifetime()]);
    }

    #[Route('/admin/reinitialisation/{token}', name: 'app_admin_reset_password', defaults: ['token' => null], methods: ['GET', 'POST'])]
    public function reset(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $entityManager,
        ?string $token = null,
    ): Response {
        if (null !== $token) {
            // Le jeton quitte l'adresse (donc l'historique et le Referer) : il est gardé en session.
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('app_admin_reset_password');
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException();
        }

        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface) {
            $this->cleanSessionAfterReset();
            $this->addFlash('error', 'admin.reset.invalid');

            return $this->redirectToRoute('app_admin_forgot_password');
        }
        \assert($user instanceof User);

        $form = $this->createForm(NewPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->resetPasswordHelper->removeResetRequest($token);
            $user->setPassword($hasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            $entityManager->flush();
            $this->cleanSessionAfterReset();
            $this->addFlash('success', 'admin.reset.done');

            // La double authentification reste exigée à la prochaine connexion : une réinitialisation ne la contourne pas.
            return $this->redirectToRoute('app_admin_login');
        }

        return $this->render('admin/security/reset_password.html.twig', ['form' => $form], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * Enrôlement de l'application d'authentification. Réservé à un utilisateur complètement connecté qui n'a pas encore de
     * secret : un compte déjà protégé ne peut pas remplacer son secret par cette page (il faudrait déjà avoir passé la 2FA).
     */
    #[Route('/admin/securite/double-authentification', name: 'app_admin_2fa_setup', methods: ['GET', 'POST'])]
    public function setupTwoFactor(Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        if ($user->isTotpAuthenticationEnabled()) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $session = $request->getSession();
        $secret = $session->get(self::SETUP_SECRET);
        if (!\is_string($secret) || '' === $secret) {
            $secret = TOTP::generate()->getSecret();
            $session->set(self::SETUP_SECRET, $secret);
        }

        $totp = TOTP::createFromSecret($secret);
        $totp->setLabel($user->getUserIdentifier());
        $totp->setIssuer('Portail MCM');

        $error = null;
        if ($request->isMethod(Request::METHOD_POST)) {
            if (!$this->isCsrfTokenValid('admin_2fa_setup', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }
            $code = preg_replace('/\s+/', '', (string) $request->request->get('code'));
            if (1 === preg_match('/^\d{6}$/', (string) $code) && $totp->verify((string) $code, null, 15)) {
                // Le secret n'est enregistré qu'une fois le premier code vérifié : jamais d'enrôlement à moitié fait.
                $user->setTotpSecret($secret);
                $entityManager->flush();
                $session->remove(self::SETUP_SECRET);
                $this->addFlash('success', 'admin.2fa.enabled');

                return $this->redirectToRoute('app_admin_dashboard');
            }
            $error = $translator->trans('admin.2fa.invalid_code');
        }

        return $this->render('admin/security/two_factor_setup.html.twig', [
            'secret' => chunk_split($secret, 4, ' '),
            'uri' => $totp->getProvisioningUri(),
            'error' => $error,
        ], new Response(status: null === $error ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
