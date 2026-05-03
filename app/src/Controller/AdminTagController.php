<?php

namespace App\Controller;

use App\Entity\Tag;
use App\Form\TagType;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/tags')]
class AdminTagController extends AbstractController
{
    #[Route('', name: 'app_admin_tag_index', methods: ['GET'])]
    public function index(TagRepository $tagRepository): Response
    {
        return $this->render('admin/tag/index.html.twig', [
            'tags' => $tagRepository->findAllOrdered(),
        ]);
    }

    #[Route('/new', name: 'app_admin_tag_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $tag = new Tag();
        $form = $this->createForm(TagType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($tag);
            $entityManager->flush();
            $this->addFlash('success', 'Tag cree.');

            return $this->redirectToRoute('app_admin_tag_index');
        }

        return $this->render('admin/tag/form.html.twig', [
            'tag' => $tag,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_tag_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Tag $tag, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TagType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Tag mis a jour.');

            return $this->redirectToRoute('app_admin_tag_index');
        }

        return $this->render('admin/tag/form.html.twig', [
            'tag' => $tag,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/disable', name: 'app_admin_tag_disable', methods: ['POST'])]
    public function disable(Request $request, Tag $tag, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('disable_tag_'.$tag->getId(), (string) $request->request->get('_token'))) {
            $tag->setIsActive(false);
            $entityManager->flush();
            $this->addFlash('success', 'Tag desactive.');
        }

        return $this->redirectToRoute('app_admin_tag_index');
    }
}
