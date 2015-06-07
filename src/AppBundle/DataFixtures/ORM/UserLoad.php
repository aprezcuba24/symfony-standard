<?php

/**
 * @author: Renier Ricardo Figueredo
 * @mail: aprezcuba24@gmail.com
 */

namespace AppBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Doctrine\Common\Persistence\ObjectManager;

class UserLoad extends AbstractFixture implements ContainerAwareInterface, OrderedFixtureInterface
{
    private $container;

    function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }
    public function getOrder()
    {
        return 100;
    }
    public function load(ObjectManager $manager)
    {
        $user = new \AppBundle\Entity\User\User();
        $user->setName('admin');
        $user->setUsername('admin');
        $user->setPlainPassword('admin');
        $user->setRoles(array('ROLE_SUPER_ADMIN'));
        $user->setEmail('admin@gestionpublicacion.localhost');
        $user->setEnabled(true);
        $manager->persist($user);
        $this->addReference('admin', $user);

        $manager->flush();
    }
}