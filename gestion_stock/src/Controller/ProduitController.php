<?php

namespace App\Controller;

use App\Entity\Produit;
use App\Form\ProduitType;
use App\Repository\ProduitRepository;
use App\Service\ProduitService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/produit')]
class ProduitController extends AbstractController
{
    #[Route('/', name: 'app_produit_index', methods: ['GET'])]
    public function index(ProduitRepository $produitRepository): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            $produits = $produitRepository->findActifs();
        } else {
            $produits = $produitRepository->findActifsByUser($this->getUser());
        }

        return $this->render('produit/index.html.twig', [
            'produits' => $produits,
        ]);
    }

    #[Route('/new', name: 'app_produit_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    #[Route('/produit/new', name: 'produit_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $produit = new Produit();
        $produit->setUtilisateur($this->getUser());
        
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($produit);
            $em->flush();
            $this->addFlash('success', 'Produit ajouté avec succès !');

            return $this->redirectToRoute('app_produit_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('produit/new.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_produit_show', methods: ['GET'])]
    #[Route('/produit/{id}', name: 'produit_show')]
    public function show(Produit $produit): Response
    {
        return $this->render('produit/show.html.twig', [
            'produit' => $produit,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_produit_edit', methods: ['GET', 'POST'])]
    #[Route('/produit/{id}/edit', name: 'produit_edit')]
    public function edit(Request $request, Produit $produit, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Produit modifié avec succès !');
            return $this->redirectToRoute('produit_index');
        }

        return $this->render('produit/edit.html.twig', [
            'form' => $form->createView(),
            'produit' => $produit,
        ]);
    }

    #[Route('/{id}', name: 'app_produit_delete', methods: ['POST'])]
    public function delete(Request $request, Produit $produit, EntityManagerInterface $entityManager): Response
    {
        // Vérifier que l'utilisateur peut supprimer ce produit
        if (!$this->isGranted('ROLE_ADMIN') && $produit->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$produit->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($produit);
            $entityManager->flush();
            
            $this->addFlash('success', 'Le produit a été supprimé avec succès !');
        }

        return $this->redirectToRoute('app_produit_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/vendre', name: 'app_produit_vendre', methods: ['POST'])]
    public function vendre(Request $request, Produit $produit, ProduitService $produitService): Response
    {
        // Vérifier que l'utilisateur peut vendre ce produit
        if (!$this->isGranted('ROLE_ADMIN') && $produit->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $quantite = (int) $request->request->get('quantite', 1);
        
        if ($produitService->vendre($produit, $quantite)) {
            $this->addFlash('success', "Vente de {$quantite} unité(s) effectuée avec succès !");
        } else {
            $this->addFlash('error', 'Impossible d\'effectuer cette vente. Quantité insuffisante.');
        }

        return $this->redirectToRoute('app_produit_show', ['id' => $produit->getId()]);
    }
}