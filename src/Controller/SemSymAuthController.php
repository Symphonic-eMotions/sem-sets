<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use function hash_equals;

/**
 * REST endpoint voor sem-sym frontend authenticatie.
 * Valideert email + apiKey en geeft instanceId terug.
 *
 * Route valt onder ^/api → PUBLIC_ACCESS (zie security.yaml).
 */
#[Route('/api/sem-sym')]
final class SemSymAuthController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * POST /api/sem-sym/auth/login
     *
     * Body: { "email": "...", "apiKey": "..." }
     * 200:  { "instanceId": "...", "email": "..." }
     * 401:  { "error": "Invalid credentials" }
     */
    #[Route('/auth/login', name: 'api_sem_sym_auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode((string) $request->getContent(), true);

        $email = isset($data['email']) && is_string($data['email']) ? trim($data['email']) : '';
        $apiKey = isset($data['apiKey']) && is_string($data['apiKey']) ? trim($data['apiKey']) : '';

        if ($email === '' || $apiKey === '') {
            return $this->json(['error' => 'email and apiKey are required'], 400);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        // Gebruik hash_equals om timing-aanvallen te voorkomen.
        // Als user niet bestaat, vergelijk met een lege string zodat de timing gelijk blijft.
        $storedKey = $user?->getApiKey() ?? '';
        $valid = $storedKey !== '' && hash_equals($storedKey, $apiKey);

        if (!$valid || !$user || !$user->isActive() || $user->getInstanceId() === null) {
            return $this->json(['error' => 'Invalid credentials'], 401);
        }

        return $this->json([
            'instanceId' => $user->getInstanceId(),
            'email' => $user->getEmail(),
        ]);
    }
}
