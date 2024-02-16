<?php

namespace App\Controller;

use App\Entity\Game;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

class GameController extends AbstractController
{
    #[Route('/api/game', name: 'game.getAll', methods: ['GET'])]
    /* #[IsGranted('IS_AUTHENTICATED_FULLY')] */
    public function getAllGame(GameRepository $repository, SerializerInterface $serializer, TagAwareCacheInterface $cache): JsonResponse{
        
        $idCache = "getAllGame";
        $cache->invalidateTags(["gameCache"]);
        $jsonGame= $cache->get($idCache, function(ItemInterface $item) use($repository, $serializer){
            
            $item->tag("gameCache");
            $games = $repository->findAll();
            return $serializer->serialize($games,'json', ['groups'=> "getAll"]);
        });
        
        return new JsonResponse($jsonGame,200,[],true);
    }

    #[Route('/api/game/{idGame}', name: 'game.get', methods: ['GET'])]
    #[ParamConverter("game", options: ["id" => "idGame"])]
    
    public function getGame(Game $game, SerializerInterface $serializer): JsonResponse{
       /*  $repository->findByStatus("on", $idGame); */
        $jsonGame= $serializer->serialize($game,'json', ['groups'=> "getAll"]);
        return new JsonResponse($jsonGame,200,[],true);
        /* dd($films);//equivalent de console.log */
    }

    /**
     * Create new game
     *
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $manager
     * @param UrlGeneratorInterface $urlGenerator
     * @return JsonResponse
     */
    #[Route('/api/game', name: 'game.post', methods: ['POST'])]
    public function createGame(Request $request,  SerializerInterface $serializer, EntityManagerInterface $entityManager, UrlGeneratorInterface $urlGenerator,GameRepository $gameRepository, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse{
        $game = $serializer->deserialize($request->getContent(), Game::class,'json');
        $dateNow = new \DateTime();
        
        $plateforme = $request->toArray()["plateformes"];
        /* dd($plateformes); */
        if(!is_null($plateforme) && $plateforme instanceof Game){
            $game->addEvolution($plateforme);
        }
        

        $game
        ->setStatus("on")
        ->setCreateAt($dateNow)
        ->setDateSortie($dateNow)
        ->setUpdateAt($dateNow);
    
        $errors = $validator->validate($game);
        if($errors ->count() > 0){
            return new JsonResponse($serializer->serialize($errors,'json'),JsonResponse::HTTP_BAD_REQUEST,[],true);
        }
        
        $entityManager->persist($game);
        $entityManager->flush();
        $cache->invalidateTags(["gameCache"]);

        $jsonGame= $serializer->serialize($game,'json');

        $location = $urlGenerator->generate('game.get', ['idGame'=> $game->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonGame,Response::HTTP_CREATED,["Location" => $location],true);

    }

    /** 
     * Update Films with a id
     *
     * @param Game $game
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $entityManager
     * @param UrlGeneratorInterface $urlGenerator
     * @return JsonResponse
     */
    #[Route('/api/film/{id}', name: 'film.update', methods: ['PUT'])]
    public function updateGame(Game $game, Request $request,  SerializerInterface $serializer, EntityManagerInterface $entityManager, TagAwareCacheInterface $cache): JsonResponse{

        $updatedFilm = $serializer->deserialize($request->getContent(), Game::class,'json', [AbstractNormalizer::OBJECT_TO_POPULATE =>$game]);
        $updatedFilm->setUpdateAt(new \DateTime());
        $entityManager->persist($updatedFilm);
        $entityManager->flush();
        $cache->invalidateTags(["gameCache"]);
        return new JsonResponse(null,JsonResponse::HTTP_NO_CONTENT,[],false);

    }

    /** 
     * Update Films with a id
     *
     * @param Game $games
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $entityManager
     * @param UrlGeneratorInterface $urlGenerator
     * @return JsonResponse
     */
    #[Route('/api/game/{id}', name: 'game.delete', methods: ['DELETE'])]
    public function softDeleteGame(Game $games, Request $request, EntityManagerInterface $entityManager, TagAwareCacheInterface $cache): JsonResponse{
        
        $game = $request->toArray()["force"];
        if($game === true){
            $entityManager->remove($games);
            
        }else{
            $game->setUpdateAt(new \DateTime())
            ->setStatus("off");
            $entityManager->persist($game);
        }
        $entityManager->flush();
        $cache->invalidateTags(["gameCache"]);
        return new JsonResponse(null,JsonResponse::HTTP_NO_CONTENT,[],false);

    }
    /* #[Route('/game', name: 'app_game')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/GameController.php',
        ]);
    } */
}
