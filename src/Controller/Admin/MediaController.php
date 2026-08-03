<?php

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Form\MediaType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class MediaController extends AbstractController
{
    private const PER_PAGE = 25;

    public function __construct(private readonly ManagerRegistry $managerRegistry)
    {
    }

    #[Route('/admin/media', name: 'admin_media_index')]
    public function index(Request $request)
    {
        $page = max(1, $request->query->getInt('page', 1));

        $criteria = [];

        if (!$this->isGranted('ROLE_ADMIN')) {
            $criteria['user'] = $this->getUser();
        }

        $repository = $this->managerRegistry->getRepository(Media::class);
        $medias = $repository->findBy(
            $criteria,
            ['id' => 'ASC'],
            self::PER_PAGE,
            self::PER_PAGE * ($page - 1)
        );
        $total = $repository->count($criteria);

        return $this->render('admin/media/index.html.twig', [
            'medias' => $medias,
            'total' => $total,
            'page' => $page,
            'perPage' => self::PER_PAGE
        ]);
    }

    #[Route('/admin/media/add', name: 'admin_media_add')]
    public function add(Request $request)
    {
        $media = new Media();
        $form = $this->createForm(MediaType::class, $media, ['is_admin' => $this->isGranted('ROLE_ADMIN')]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->isGranted('ROLE_ADMIN')) {
                $media->setUser($this->getUser());
            }
            $media->setPath('uploads/' . md5(uniqid()) . '.' . $media->getFile()->guessExtension());
            $media->getFile()->move('uploads/', $media->getPath());
            $this->managerRegistry->getManager()->persist($media);
            $this->managerRegistry->getManager()->flush();

            return $this->redirectToRoute('admin_media_index');
        }

        return $this->render('admin/media/add.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/admin/media/delete/{id}', name: 'admin_media_delete')]
    public function delete(int $id)
    {
        $media = $this->managerRegistry->getRepository(Media::class)->find($id);
        $this->managerRegistry->getManager()->remove($media);
        $this->managerRegistry->getManager()->flush();
        unlink($media->getPath());

        return $this->redirectToRoute('admin_media_index');
    }
}