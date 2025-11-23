<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\EffectSettings;
use App\Form\EffectSettingsType;
use App\Repository\EffectSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/effects')]
final class EffectSettingsController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', name: 'effects_index', methods: ['GET'])]
    public function index(EffectSettingsRepository $repo): Response
    {
        return $this->render('Effect/index.html.twig', [
            'effects' => $repo->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'effects_new', methods: ['GET','POST'])]
    public function new(Request $req): Response
    {
        $effect = new EffectSettings();
        $form = $this->createForm(EffectSettingsType::class, $effect);
        $form->handleRequest($req);

        if (!$form->isSubmitted()) {
            $form->get('config')->setData($effect->getConfigAsPrettyJson());
        }

        if ($form->isSubmitted()) {
            $rawConfig = (string) $form->get('config')->getData();

            if ($rawConfig !== '') {
                $decoded = json_decode($rawConfig, true);
                if (!is_array($decoded)) {
                    $form->get('config')->addError(new FormError('JSON is ongeldig.'));
                    $this->addFlash('danger', 'Effect JSON is ongeldig. Niets opgeslagen.');
                } else {
                    $effect->setConfig($decoded);
                }
            }

            if ($form->isValid()) {
                $this->em->persist($effect);
                $this->em->flush();
                $this->addFlash('success', 'Effect preset opgeslagen.');
                return $this->redirectToRoute('effects_index');
            }

            return $this->render('Effect/edit.html.twig', [
                'form' => $form->createView(),
                'effect' => $effect,
            ], new Response('', 422));
        }

        return $this->render('Effect/edit.html.twig', [
            'form' => $form->createView(),
            'effect' => $effect,
        ]);
    }

    #[Route('/{id}/edit', name: 'effects_edit', methods: ['GET','POST'])]
    public function edit(EffectSettings $effect, Request $req): Response
    {
        $form = $this->createForm(EffectSettingsType::class, $effect);
        $form->handleRequest($req);

        if (!$form->isSubmitted()) {
            $form->get('config')->setData($effect->getConfigAsPrettyJson());
        }

        if ($form->isSubmitted()) {
            $rawConfig = (string) $form->get('config')->getData();

            if ($rawConfig !== '') {
                $decoded = json_decode($rawConfig, true);
                if (!is_array($decoded)) {
                    $form->get('config')->addError(
                        new FormError('JSON is ongeldig.')
                    );
                    $this->addFlash('danger', 'Effect JSON is ongeldig. Niets opgeslagen.');
                } else {
                    $effect->setConfig($decoded);
                }
            }

            if ($form->isValid()) {
                $this->em->flush();
                $this->addFlash('success', 'Effect preset bijgewerkt.');
                return $this->redirectToRoute('effects_index');
            }

            return $this->render('Effect/edit.html.twig', [
                'form' => $form->createView(),
                'effect' => $effect,
            ], new Response('', 422));
        }

        return $this->render('Effect/edit.html.twig', [
            'form' => $form->createView(),
            'effect' => $effect,
        ]);
    }

    #[Route('/{id}/delete', name: 'effects_delete', methods: ['POST'])]
    public function delete(EffectSettings $effect, Request $req): Response
    {
        if (!$this->isCsrfTokenValid('delete-effect-'.$effect->getId(), $req->request->get('_token'))) {
            $this->addFlash('danger', 'Ongeldige CSRF token.');
            return $this->redirectToRoute('effects_index');
        }

        $this->em->remove($effect);
        $this->em->flush();
        $this->addFlash('success', 'Effect preset verwijderd.');
        return $this->redirectToRoute('effects_index');
    }
}
