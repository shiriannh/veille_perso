<?php

namespace App\Controller;

use App\Repository\EntryRepository;
use App\Service\EntryAnalyzer;
use App\Service\MediaTypeResolver;
use App\Service\RssCategoryMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/media')]
class AdminMediaController extends AbstractController
{
    #[Route('/diagnostic', name: 'app_admin_media_diagnostic', methods: ['GET'])]
    public function diagnostic(EntryRepository $entryRepository): Response
    {
        return $this->render('admin/media/diagnostic.html.twig', [
            'entries' => $entryRepository->findStoredOtherMediaEntries(),
        ]);
    }

    #[Route('/diagnostic/reanalyze-other', name: 'app_admin_media_reanalyze_other', methods: ['POST'])]
    public function reanalyzeOther(
        Request $request,
        EntryRepository $entryRepository,
        EntryAnalyzer $entryAnalyzer,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('reanalyze_other_media', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide, reanalyse non lancee.');

            return $this->redirectToRoute('app_admin_media_diagnostic');
        }

        $entries = $entryRepository->findStoredOtherMediaEntries();
        foreach ($entries as $entry) {
            $entryAnalyzer->analyze($entry);
        }

        $entityManager->flush();

        $this->addFlash('success', sprintf('%d entree(s) avec media other reanalysees.', count($entries)));

        return $this->redirectToRoute('app_admin_media_diagnostic');
    }

    #[Route('/mappings', name: 'app_admin_media_mappings', methods: ['GET'])]
    public function mappings(): Response
    {
        return $this->render('admin/media/mappings.html.twig', [
            'origins' => MediaTypeResolver::origins(),
            'tag_media_mappings' => MediaTypeResolver::tagMediaMappings(),
            'source_profiles' => MediaTypeResolver::sourceProfiles(),
            'rss_media_mappings' => RssCategoryMapper::mediaMappings(),
            'rss_tag_mappings' => RssCategoryMapper::tagMappings(),
        ]);
    }
}
