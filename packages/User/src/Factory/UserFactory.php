<?php

namespace Solidarity\User\Factory;

use Doctrine\ORM\EntityManager;
use Solidarity\Delegate\Entity\Delegate;
use Solidarity\School\Entity\School;
use Solidarity\User\Entity\User;

class UserFactory extends \Skeletor\User\Factory\UserFactory
{
    public static function compileEntityForUpdate($data, $entityManager)
    {
        $user = $entityManager->getRepository(\Solidarity\User\Entity\User::class)->find($data['id']);
        $user->firstName = $data['firstName'];
        $user->lastName = $data['lastName'];
        $user->email = $data['email'];
        $user->role = $data['role'];
        $user->isActive = $data['isActive'];
        $user->displayName = $data['displayName'];
        $entityManager->persist($user);

        return $user->getId();
    }

    public static function compileEntityForCreate($data, $entityManager)
    {
        $user = new \Solidarity\User\Entity\User();
        $user->firstName = $data['firstName'];
        $user->lastName = $data['lastName'];
        $user->email = $data['email'];
        $user->role = $data['role'];
        $user->isActive = $data['isActive'];
        $user->displayName = $data['displayName'];
        $entityManager->persist($user);
        $entityManager->flush();

        return $user->getId();
    }
}