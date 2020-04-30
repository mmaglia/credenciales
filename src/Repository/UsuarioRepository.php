<?php

namespace App\Repository;

use App\Entity\Usuario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @method Usuario|null find($id, $lockMode = null, $lockVersion = null)
 * @method Usuario|null findOneBy(array $criteria, array $orderBy = null)
 * @method Usuario[]    findAll()
 * @method Usuario[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UsuarioRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Usuario::class);
    }

    /**
     * Retorna todos los registros ordenados por $column
     */
    public function findAllOrderedByColum($column, $sort = 'ASC')
    {
        return $this->createQueryBuilder('t')
            ->addOrderBy('t.' . $column, $sort)
            ->getQuery()
            ->getResult();
    } 

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(UserInterface $user, string $newEncodedPassword): void
    {
        if (!$user instanceof Usuario) {
            throw new UnsupportedUserException(sprintf('Las instancias de "%s" no son compatibles.', \get_class($user)));
        }

        $user->setPassword($newEncodedPassword);
        $this->_em->persist($user);
        $this->_em->flush();
    }

    /**
     * Actualizar fecha de ultimo logueo y contabilizador de entradas al sistema
     * @param Usuario
     */
    public function actualizarLogueoUsuario(Usuario $usuario)
    {
        $usuario->setUltimoAcceso(new \DateTime('now'));
        $cantidadAnterior = $usuario->getCantidadAccesos() != null ? $usuario->getCantidadAccesos() : 0;
        $usuario->setCantidadAccesos($cantidadAnterior + 1);
        $this->_em->persist($usuario);
        $this->_em->flush();
    }

    // /**
    //  * @return Usuario[] Returns an array of Usuario objects
    //  */
    /*
    public function findByExampleField($value)
    {
    return $this->createQueryBuilder('u')
    ->andWhere('u.exampleField = :val')
    ->setParameter('val', $value)
    ->orderBy('u.id', 'ASC')
    ->setMaxResults(10)
    ->getQuery()
    ->getResult()
    ;
    }
     */

    /*
public function findOneBySomeField($value): ?Usuario
{
return $this->createQueryBuilder('u')
->andWhere('u.exampleField = :val')
->setParameter('val', $value)
->getQuery()
->getOneOrNullResult()
;
}
 */
}
