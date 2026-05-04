<?php

namespace App\Controller;

use App\Entity\InterestProfileRule;
use App\Form\InterestProfileRuleType;
use App\Repository\InterestProfileRuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/interest-profile')]
class AdminInterestProfileController extends AbstractController
{
    #[Route('', name: 'app_admin_interest_profile_index', methods: ['GET'])]
    public function index(InterestProfileRuleRepository $repository): Response
    {
        return $this->render('admin/interest_profile/index.html.twig', [
            'rules' => $repository->findAllOrdered(),
        ]);
    }

    #[Route('/new', name: 'app_admin_interest_profile_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $rule = new InterestProfileRule();
        $form = $this->createForm(InterestProfileRuleType::class, $rule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($rule);
            $entityManager->flush();
            $this->addFlash('success', 'Regle de profil creee.');

            return $this->redirectToRoute('app_admin_interest_profile_index');
        }

        return $this->render('admin/interest_profile/form.html.twig', [
            'rule' => $rule,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_interest_profile_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, InterestProfileRule $rule, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(InterestProfileRuleType::class, $rule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Regle de profil mise a jour.');

            return $this->redirectToRoute('app_admin_interest_profile_index');
        }

        return $this->render('admin/interest_profile/form.html.twig', [
            'rule' => $rule,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle', name: 'app_admin_interest_profile_toggle', methods: ['POST'])]
    public function toggle(Request $request, InterestProfileRule $rule, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle_interest_rule_'.$rule->getId(), (string) $request->request->get('_token'))) {
            $rule->setIsActive(!$rule->isActive());
            $entityManager->flush();
            $this->addFlash('success', $rule->isActive() ? 'Regle activee.' : 'Regle desactivee.');
        }

        return $this->redirectToRoute('app_admin_interest_profile_index');
    }
}
