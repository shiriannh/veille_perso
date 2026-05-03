<?php

namespace App\Controller;

use App\Entity\AnalysisLanguageReference;
use App\Entity\ClickbaitLevelReference;
use App\Entity\DecisionTypeReference;
use App\Entity\FetchModeReference;
use App\Entity\MediaTypeReference;
use App\Entity\ReferenceEntityInterface;
use App\Entity\SourceTypeReference;
use App\Form\ReferenceValueType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/references/{type}')]
class AdminReferenceController extends AbstractController
{
    /**
     * @var array<string, array{class: class-string<ReferenceEntityInterface>, label: string}>
     */
    private const TYPES = [
        'media-types' => ['class' => MediaTypeReference::class, 'label' => 'Media types'],
        'source-types' => ['class' => SourceTypeReference::class, 'label' => 'Source types'],
        'fetch-modes' => ['class' => FetchModeReference::class, 'label' => 'Fetch modes'],
        'decision-types' => ['class' => DecisionTypeReference::class, 'label' => 'Decision types'],
        'clickbait-levels' => ['class' => ClickbaitLevelReference::class, 'label' => 'Clickbait levels'],
        'analysis-languages' => ['class' => AnalysisLanguageReference::class, 'label' => 'Analysis languages'],
    ];

    #[Route('', name: 'app_admin_reference_index', methods: ['GET'])]
    public function index(string $type, EntityManagerInterface $entityManager): Response
    {
        $config = $this->config($type);

        return $this->render('admin/reference/index.html.twig', [
            'type' => $type,
            'label' => $config['label'],
            'items' => $entityManager->getRepository($config['class'])->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'app_admin_reference_new', methods: ['GET', 'POST'])]
    public function new(string $type, Request $request, EntityManagerInterface $entityManager): Response
    {
        $config = $this->config($type);
        $item = new $config['class']();
        $form = $this->createForm(ReferenceValueType::class, $item, ['data_class' => $config['class']]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($item);
            $entityManager->flush();
            $this->addFlash('success', 'Reference creee.');

            return $this->redirectToRoute('app_admin_reference_index', ['type' => $type]);
        }

        return $this->render('admin/reference/form.html.twig', [
            'type' => $type,
            'label' => $config['label'],
            'form' => $form,
            'item' => $item,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_reference_edit', methods: ['GET', 'POST'])]
    public function edit(string $type, int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $config = $this->config($type);
        $item = $entityManager->getRepository($config['class'])->find($id);
        if (!$item instanceof ReferenceEntityInterface) {
            throw $this->createNotFoundException('Reference introuvable.');
        }

        $form = $this->createForm(ReferenceValueType::class, $item, ['data_class' => $config['class']]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Reference mise a jour.');

            return $this->redirectToRoute('app_admin_reference_index', ['type' => $type]);
        }

        return $this->render('admin/reference/form.html.twig', [
            'type' => $type,
            'label' => $config['label'],
            'form' => $form,
            'item' => $item,
        ]);
    }

    #[Route('/{id}/disable', name: 'app_admin_reference_disable', methods: ['POST'])]
    public function disable(string $type, int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $config = $this->config($type);
        $item = $entityManager->getRepository($config['class'])->find($id);
        if (!$item instanceof ReferenceEntityInterface) {
            throw $this->createNotFoundException('Reference introuvable.');
        }

        if ($this->isCsrfTokenValid('disable_reference_'.$type.'_'.$id, (string) $request->request->get('_token'))) {
            $item->setIsActive(false);
            $entityManager->flush();
            $this->addFlash('success', 'Reference desactivee.');
        }

        return $this->redirectToRoute('app_admin_reference_index', ['type' => $type]);
    }

    /**
     * @return array{class: class-string<ReferenceEntityInterface>, label: string}
     */
    private function config(string $type): array
    {
        if (!isset(self::TYPES[$type])) {
            throw $this->createNotFoundException('Type de reference inconnu.');
        }

        return self::TYPES[$type];
    }
}
