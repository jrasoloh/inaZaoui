<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\GuestType;
use App\Service\MediaUploader;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/guest')]
class GuestController extends AbstractController
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly MediaUploader $mediaUploader,
    ) {
    }

    #[Route('', name: 'admin_guest_index')]
    public function index(): Response
    {
        $guests = $this->managerRegistry->getRepository(User::class)
            ->findBy(['admin' => false], ['name' => 'ASC']);

        return $this->render('admin/guest/index.html.twig', ['guests' => $guests]);
    }

    #[Route('/add', name: 'admin_guest_add')]
    public function add(Request $request, UserPasswordHasherInterface $hasher): Response
    {
        $guest = new User();
        $form = $this->createForm(GuestType::class, $guest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $guest->setAdmin(false);
            $guest->setActive(true);
            $guest->setPassword($hasher->hashPassword($guest, $form->get('plainPassword')->getData()));

            $em = $this->managerRegistry->getManager();
            $em->persist($guest);
            $em->flush();

            $this->addFlash('success', 'Invité ajouté.');

            return $this->redirectToRoute('admin_guest_index');
        }

        return $this->render('admin/guest/add.html.twig', ['form' => $form->createView()]);
    }

    /**
     * Toggle a single guest's access (block / unblock).
     */
    #[Route('/toggle/{id}', name: 'admin_guest_toggle')]
    public function toggle(int $id): Response
    {
        $guest = $this->managerRegistry->getRepository(User::class)->find($id);

        if (null !== $guest && !$guest->isAdmin()) {
            $guest->setActive(!$guest->isActive());
            $this->managerRegistry->getManager()->flush();
            $this->addFlash('success', $guest->isActive() ? 'Accès rétabli.' : 'Accès bloqué.');
        }

        return $this->redirectToRoute('admin_guest_index');
    }

    /**
     * Revoke access for the selected guests (bulk).
     */
    #[Route('/revoke', name: 'admin_guest_revoke', methods: ['POST'])]
    public function revoke(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('revoke_guests', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $ids = array_map('intval', $request->request->all('guests'));

        if ($ids) {
            $repository = $this->managerRegistry->getRepository(User::class);
            foreach ($ids as $id) {
                $guest = $repository->find($id);
                if (null !== $guest && !$guest->isAdmin()) {
                    $guest->setActive(false);
                }
            }
            $this->managerRegistry->getManager()->flush();
            $this->addFlash('success', sprintf('%d accès révoqué(s).', count($ids)));
        }

        return $this->redirectToRoute('admin_guest_index');
    }

    /**
     * Delete a guest together with all their media (DB rows + physical files).
     */
    #[Route('/delete/{id}', name: 'admin_guest_delete')]
    public function delete(int $id): Response
    {
        $guest = $this->managerRegistry->getRepository(User::class)->find($id);

        if (null !== $guest && !$guest->isAdmin()) {
            // Remove physical files first; DB rows are removed by the cascade.
            foreach ($guest->getMedias() as $media) {
                $this->mediaUploader->remove($media->getPath());
            }

            $em = $this->managerRegistry->getManager();
            $em->remove($guest);
            $em->flush();

            $this->addFlash('success', 'Invité et médias associés supprimés.');
        }

        return $this->redirectToRoute('admin_guest_index');
    }
}

