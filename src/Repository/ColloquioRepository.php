<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\Repository;

use Doctrine\ORM\EntityRepository;
use App\Entity\Docente;


/**
 * Colloquio - repository
 */
class ColloquioRepository extends BaseRepository {

  /**
   * Restituisce la lista dei colloqui secondo i criteri di ricerca indicati
   *
   * @param array $search Lista dei criteri di ricerca
   * @param int $page Pagina corrente
   * @param int $limit Numero di elementi per pagina
   *
   * @return Paginator Oggetto Paginator
   */
  public function findAll($search=null, $page=1, $limit=10) {
    // crea query base
    $query = $this->createQueryBuilder('c')
      ->select("c AS colloquio,s.citta AS sede,CONCAT(d.cognome,' ',d.nome) AS docente,so.inizio,so.fine")
      ->join('c.docente', 'd')
      ->join('c.orario', 'o')
      ->join('o.sede', 's')
      ->join('App:ScansioneOraria', 'so', 'WITH', 'so.orario=o.id AND so.giorno=c.giorno AND so.ora=c.ora')
      ->where('d.abilitato=:abilitato')
      ->orderBy('s.id,d.cognome,d.nome', 'ASC')
      ->setParameter('abilitato', 1);
    if ($search['docente'] > 0) {
      $query->andWhere('d.id=:docente')->setParameter('docente', $search['docente']);
    }
    // crea lista con pagine
    $res = $this->paginazione($query->getQuery(), $page);
    return $res['lista'];
  }

  /**
   * Restituisce le ore dei colloqui individuali del docente
   *
   * @param Docente $docente Docente di cui visualizzare le ore di colloquio
   *
   * @return array Dati restituiti
   */
  public function ore(Docente $docente) {
    $colloqui = $this->createQueryBuilder('c')
      ->select('c.frequenza,c.giorno,c.note,s.citta,so.inizio,so.fine')
      ->join('c.orario', 'o')
      ->join('o.sede', 's')
      ->join('App:ScansioneOraria', 'so', 'WITH', 'so.orario=o.id AND so.giorno=c.giorno AND so.ora=c.ora')
      ->where('c.docente=:docente')
      ->orderBy('s.id', 'ASC')
      ->setParameters(['docente' => $docente])
      ->getQuery()
      ->getArrayResult();
    return $colloqui;
  }

  /**
   * Restituisce la lista dei colloqui secondo i criteri di ricerca indicati
   *
   * @param array $criteri Lista dei criteri di ricerca
   * @param int $pagina Pagina corrente
   *
   * @return array Array associativo con i risultati della ricerca
   */
  public function cerca($criteri, $pagina=1) {
    // crea query
    $query = $this->createQueryBuilder('c')
      ->select("c AS colloquio,s.citta AS sede,CONCAT(d.cognome,' ',d.nome,' (',d.username,')') AS docente,so.inizio,so.fine")
      ->join('c.docente', 'd')
      ->join('c.orario', 'o')
      ->join('o.sede', 's')
      ->join('App:ScansioneOraria', 'so', 'WITH', 'so.orario=o.id AND so.giorno=c.giorno AND so.ora=c.ora')
      ->where('d.abilitato=:abilitato')
      ->orderBy('s.ordinamento,d.cognome,d.nome,d.username', 'ASC')
      ->setParameter('abilitato', 1);
    if ($criteri['sede'] > 0) {
      $query->andWhere('s.id=:sede')->setParameter('sede', $criteri['sede']);
    } elseif ($criteri['classe'] > 0) {
      $query
        ->join('App:Cattedra', 'ct', 'WITH', 'ct.docente=d.id AND ct.classe=:classe AND ct.attiva=:attiva')
        ->setParameter('classe', $criteri['classe'])
        ->setParameter('attiva', 1);
    } elseif ($criteri['docente'] > 0) {
      $query->andWhere('d.id=:docente')->setParameter('docente', $criteri['docente']);
    }
    // crea lista con pagine
    return $this->paginazione($query->getQuery(), $pagina);
  }

}
